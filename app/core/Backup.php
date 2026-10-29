<?php
/**
 * Zinkly Social Lite - backups. One .zip in data/backups/ (never served to the web) with:
 *   manifest.json   what is inside, the version, the database type
 *   database.sql    MySQL / MariaDB: every Zinkly table, one SQL statement per line
 *   database.sqlite SQLite: a consistent copy of the database file
 *   files/...       the program files (and data/config.php); uploads/ only when asked
 */
final class Backup
{
    public const PARTS = ['database', 'files', 'uploads'];

    public static function dir(): string
    {
        $d = ZL_ROOT . '/data/backups';
        if (!is_dir($d)) {
            @mkdir($d, 0755, true);
            @file_put_contents($d . '/index.html', '');
        }
        return $d;
    }

    /** Backup file names are made here only, so a name from a URL is checked against this pattern. */
    public static function valid(string $name): bool
    {
        return (bool) preg_match('/^backup-\d{8}-\d{6}-[a-z-]+-[a-f0-9]{6}\.zip$/', $name) && is_file(self::dir() . '/' . $name);
    }

    public static function path(string $name): string
    {
        return self::dir() . '/' . basename($name);
    }

    public static function available(): ?string
    {
        if (!class_exists('ZipArchive')) {
            return t('backup.no_zip');
        }
        if (!is_writable(ZL_ROOT . '/data')) {
            return t('install.data_not_writable');
        }
        return null;
    }

    /** All backups, newest first, with their manifest. */
    public static function all(): array
    {
        $out = [];
        foreach (glob(self::dir() . '/backup-*.zip') ?: [] as $f) {
            $m = null;
            $zip = new ZipArchive();
            if ($zip->open($f) === true) {
                $m = json_decode((string) $zip->getFromName('manifest.json'), true);
                $zip->close();
            }
            $out[] = ['name' => basename($f), 'size' => filesize($f), 'time' => filemtime($f), 'manifest' => is_array($m) ? $m : null];
        }
        usort($out, fn ($a, $b) => $b['time'] <=> $a['time']);
        return $out;
    }

    /**
     * Make a backup. $parts: any of database, files, uploads. $reason: manual, before-update, before-restore.
     * Returns the file name.
     */
    public static function create(array $parts, string $reason = 'manual'): string
    {
        if ($err = self::available()) {
            throw new RuntimeException($err);
        }
        $parts = array_values(array_intersect(self::PARTS, $parts));
        if (!$parts) {
            throw new RuntimeException(t('backup.choose'));
        }
        @set_time_limit(0);
        $name = 'backup-' . date('Ymd-His') . '-' . preg_replace('/[^a-z-]/', '', $reason) . '-' . bin2hex(random_bytes(3)) . '.zip';
        $file = self::dir() . '/' . $name;
        $tmp = [];
        $zip = new ZipArchive();
        if ($zip->open($file, ZipArchive::CREATE | ZipArchive::EXCL) !== true) {
            throw new RuntimeException(t('backup.write_failed'));
        }
        $open = true;
        try {
            $count = ['files' => 0, 'uploads' => 0, 'tables' => 0];
            if (in_array('database', $parts, true)) {
                if (DB::driver() === 'sqlite') {
                    $copy = self::dir() . '/.tmp-' . bin2hex(random_bytes(6)) . '.sqlite';
                    try {
                        DB::pdo()->exec('VACUUM INTO ' . DB::pdo()->quote($copy)); // a consistent copy, even while the site is used
                    } catch (PDOException $e) { // SQLite older than 3.27
                        DB::pdo()->exec('PRAGMA wal_checkpoint(FULL)');
                        if (!copy(self::sqlitePath(), $copy)) {
                            throw new RuntimeException(t('backup.write_failed'));
                        }
                    }
                    $zip->addFile($copy, 'database.sqlite');
                    $tmp[] = $copy;
                    $count['tables'] = count(self::tables());
                } else {
                    $sql = self::dir() . '/.tmp-' . bin2hex(random_bytes(6)) . '.sql';
                    $count['tables'] = self::dumpMysql($sql);
                    $zip->addFile($sql, 'database.sql');
                    $tmp[] = $sql;
                }
            }
            if (in_array('files', $parts, true)) {
                foreach (self::walk(ZL_ROOT, ['data', 'uploads', '.git']) as $rel) {
                    $zip->addFile(ZL_ROOT . '/' . $rel, 'files/' . $rel);
                    $count['files']++;
                }
                foreach (['data/config.php', 'data/installed.lock', 'data/.htaccess', 'data/index.html'] as $rel) {
                    if (is_file(ZL_ROOT . '/' . $rel)) {
                        $zip->addFile(ZL_ROOT . '/' . $rel, 'files/' . $rel);
                    }
                }
            }
            if (in_array('uploads', $parts, true)) {
                foreach (self::walk(ZL_ROOT . '/uploads', []) as $rel) {
                    $zip->addFile(ZL_ROOT . '/uploads/' . $rel, 'uploads/' . $rel);
                    $zip->setCompressionName('uploads/' . $rel, ZipArchive::CM_STORE); // photos are already compressed
                    $count['uploads']++;
                }
            }
            $zip->addFromString('manifest.json', json_encode([
                'app' => 'zinkly-lite', 'version' => (string) setting('version', Installer::VERSION), 'created_at' => time(), 'reason' => $reason,
                'parts' => $parts, 'driver' => DB::driver(), 'prefix' => DB::t(''), 'site' => site_name(), 'counts' => $count,
            ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
            $open = false;
            if (!$zip->close()) {
                throw new RuntimeException(t('backup.write_failed'));
            }
        } catch (Throwable $e) {
            if ($open) {
                try {
                    $zip->close();
                } catch (Throwable $e2) {
                }
            }
            @unlink($file);
            throw $e instanceof RuntimeException ? $e : new RuntimeException(t('backup.write_failed') . ' ' . $e->getMessage());
        } finally {
            foreach ($tmp as $t) {
                @unlink($t);
            }
        }
        @chmod($file, 0640);
        return $name;
    }

    /** Zinkly's own tables that exist in the database. */
    private static function tables(): array
    {
        $schema = require ZL_ROOT . '/app/schema.php';
        $out = [];
        foreach (array_keys($schema['tables']) as $t) {
            $name = DB::t($t);
            $exists = DB::driver() === 'sqlite'
                ? DB::value("SELECT name FROM sqlite_master WHERE type = 'table' AND name = ?", [$name])
                : DB::value('SELECT table_name FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = ?', [$name]);
            if ($exists) {
                $out[] = $name;
            }
        }
        return $out;
    }

    /** MySQL dump: CREATE TABLE + INSERTs, each statement on one line (quote() escapes line breaks), so it can be read back line by line. */
    private static function dumpMysql(string $file): int
    {
        $fh = fopen($file, 'wb');
        if (!$fh) {
            throw new RuntimeException(t('backup.write_failed'));
        }
        $pdo = DB::pdo();
        fwrite($fh, "-- Zinkly Social Lite backup " . date('c') . "\n");
        $tables = self::tables();
        foreach ($tables as $t) {
            $create = $pdo->query('SHOW CREATE TABLE `' . $t . '`')->fetch(PDO::FETCH_NUM)[1];
            fwrite($fh, 'DROP TABLE IF EXISTS `' . $t . "`;\n");
            fwrite($fh, str_replace(["\r", "\n"], ' ', $create) . ";\n");
            $st = $pdo->query('SELECT * FROM `' . $t . '`', PDO::FETCH_ASSOC);
            $batch = [];
            $cols = null;
            foreach ($st as $row) {
                $cols ??= '(`' . implode('`,`', array_keys($row)) . '`)';
                $batch[] = '(' . implode(',', array_map(fn ($v) => $v === null ? 'NULL' : (is_int($v) || is_float($v) ? (string) $v : $pdo->quote((string) $v)), $row)) . ')';
                if (count($batch) >= 200) {
                    fwrite($fh, 'INSERT INTO `' . $t . '` ' . $cols . ' VALUES ' . implode(',', $batch) . ";\n");
                    $batch = [];
                }
            }
            if ($batch) {
                fwrite($fh, 'INSERT INTO `' . $t . '` ' . $cols . ' VALUES ' . implode(',', $batch) . ";\n");
            }
        }
        fclose($fh);
        return count($tables);
    }

    /** Relative paths of all files under $root, skipping the given top-level folders. */
    private static function walk(string $root, array $skipTop): array
    {
        $out = [];
        if (!is_dir($root)) {
            return $out;
        }
        $it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS));
        foreach ($it as $f) {
            if (!$f->isFile() || $f->isLink()) {
                continue;
            }
            $rel = ltrim(str_replace('\\', '/', substr($f->getPathname(), strlen($root))), '/');
            if (in_array(explode('/', $rel)[0], $skipTop, true)) {
                continue;
            }
            $out[] = $rel;
        }
        sort($out);
        return $out;
    }

    /**
     * Restore a backup: database (replaced completely), program files (written over the current ones),
     * and uploads if the backup has them and $withUploads is true. data/config.php is never overwritten.
     */
    public static function restore(string $name, bool $withUploads = false): array
    {
        if (!self::valid($name)) {
            throw new RuntimeException(t('error.404'));
        }
        @set_time_limit(0);
        ignore_user_abort(true);
        $zip = new ZipArchive();
        if ($zip->open(self::path($name)) !== true) {
            throw new RuntimeException(t('backup.bad_file'));
        }
        $m = json_decode((string) $zip->getFromName('manifest.json'), true);
        if (!is_array($m) || ($m['app'] ?? '') !== 'zinkly-lite') {
            $zip->close();
            throw new RuntimeException(t('backup.bad_file'));
        }
        $done = [];
        try {
            if (in_array('files', $m['parts'], true)) {
                $done['files'] = self::extract($zip, 'files/', ZL_ROOT, ['data/config.php', 'data/installed.lock']);
            }
            if ($withUploads && in_array('uploads', $m['parts'], true)) {
                $done['uploads'] = self::extract($zip, 'uploads/', ZL_ROOT . '/uploads', []);
            }
            if (in_array('database', $m['parts'], true)) {
                if (($m['driver'] ?? '') !== DB::driver()) {
                    throw new RuntimeException(t('backup.other_db'));
                }
                $done['database'] = DB::driver() === 'sqlite' ? self::restoreSqlite($zip) : self::restoreMysql($zip);
            }
        } finally {
            $zip->close();
        }
        if (function_exists('opcache_reset')) {
            @opcache_reset();
        }
        setting('__flush');
        return $done;
    }

    /** Write every entry under $prefix in the zip to $dest (safe paths only). Returns the number of files. */
    public static function extract(ZipArchive $zip, string $prefix, string $dest, array $skip): int
    {
        $n = 0;
        for ($i = 0; $i < $zip->numFiles; $i++) {
            $entry = (string) $zip->getNameIndex($i);
            if (!str_starts_with($entry, $prefix) || str_ends_with($entry, '/')) {
                continue;
            }
            $rel = substr($entry, strlen($prefix));
            if (!self::safeRel($rel) || in_array($rel, $skip, true)) {
                continue;
            }
            $target = $dest . '/' . $rel;
            if (!is_dir(dirname($target)) && !mkdir(dirname($target), 0755, true) && !is_dir(dirname($target))) {
                throw new RuntimeException(t('update.copy_failed', ['file' => $rel]));
            }
            $in = $zip->getStream($entry);
            $tmp = $target . '.zl-new';
            $out = $in ? fopen($tmp, 'wb') : false;
            if (!$in || !$out) {
                throw new RuntimeException(t('update.copy_failed', ['file' => $rel]));
            }
            stream_copy_to_stream($in, $out);
            fclose($in);
            fclose($out);
            if (!rename($tmp, $target)) { // replace in one step, so no visitor ever gets half a file
                @unlink($tmp);
                throw new RuntimeException(t('update.copy_failed', ['file' => $rel]));
            }
            @chmod($target, 0644);
            if (function_exists('opcache_invalidate') && str_ends_with($rel, '.php')) {
                @opcache_invalidate($target, true);
            }
            $n++;
        }
        return $n;
    }

    public static function safeRel(string $rel): bool
    {
        return $rel !== '' && !str_contains($rel, '\\') && !str_contains($rel, ':') && !str_starts_with($rel, '/')
            && !in_array('..', explode('/', $rel), true) && !in_array('.', explode('/', $rel), true);
    }

    private static function restoreMysql(ZipArchive $zip): int
    {
        $in = $zip->getStream('database.sql');
        if (!$in) {
            throw new RuntimeException(t('backup.bad_file'));
        }
        $pdo = DB::pdo();
        $pdo->exec('SET FOREIGN_KEY_CHECKS = 0');
        $n = 0;
        try {
            while (($line = fgets($in)) !== false) {
                $line = rtrim($line, "\r\n");
                if ($line === '' || str_starts_with($line, '--')) {
                    continue;
                }
                $pdo->exec(rtrim($line, ';'));
                $n++;
            }
        } finally {
            fclose($in);
            $pdo->exec('SET FOREIGN_KEY_CHECKS = 1');
        }
        return $n;
    }

    private static function sqlitePath(): string
    {
        $path = (string) (zl_config()['db']['path'] ?? '');
        if ($path !== '' && !str_starts_with($path, '/') && !preg_match('~^[A-Za-z]:[\\\\/]~', $path)) {
            $path = ZL_ROOT . '/' . $path;
        }
        return $path;
    }

    private static function restoreSqlite(ZipArchive $zip): int
    {
        $path = self::sqlitePath();
        $tmp = $path . '.restore-' . bin2hex(random_bytes(4));
        $in = $zip->getStream('database.sqlite');
        $out = $in ? fopen($tmp, 'wb') : false;
        if (!$in || !$out) {
            throw new RuntimeException(t('backup.bad_file'));
        }
        stream_copy_to_stream($in, $out);
        fclose($in);
        fclose($out);
        $check = new PDO('sqlite:' . $tmp, null, null, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
        $ok = $check->query('PRAGMA integrity_check')->fetchColumn() === 'ok';
        $check = null;
        if (!$ok) {
            @unlink($tmp);
            throw new RuntimeException(t('backup.bad_file'));
        }
        DB::disconnect();
        @unlink($path . '-wal');
        @unlink($path . '-shm');
        if (!rename($tmp, $path)) {
            @unlink($tmp);
            throw new RuntimeException(t('backup.write_failed'));
        }
        DB::connect(['driver' => 'sqlite', 'path' => $path, 'prefix' => zl_config()['db']['prefix'] ?? '']);
        return 1;
    }

    public static function delete(string $name): void
    {
        if (self::valid($name)) {
            @unlink(self::path($name));
        }
    }

    public static function human(int $bytes): string
    {
        return $bytes >= 1048576 ? number_format($bytes / 1048576, 1) . ' MB' : max(1, (int) round($bytes / 1024)) . ' KB';
    }
}
