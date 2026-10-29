<?php
/**
 * Zinkly Social Lite - updates from an uploaded .zip (the same zip you download).
 *   1. upload  -> the package is checked (is it Zinkly Social Lite? which version? safe paths?) and kept in data/updates/
 *   2. preview -> new version, its changelog, how many files change
 *   3. apply   -> backup (program files + database), maintenance, copy files, then the next page view upgrades the database
 * If copying fails, the program files are put back from the backup at once.
 * data/, uploads/ and installed extensions are never touched.
 */
final class Updater
{
    public const MAX_ZIP = 64 * 1024 * 1024;
    private const KEEP = ['data/', 'uploads/', 'extensions/']; // only extensions/.htaccess and extensions/index.html are updated

    public static function dir(): string
    {
        $d = ZL_ROOT . '/data/updates';
        if (!is_dir($d)) {
            @mkdir($d, 0755, true);
            @file_put_contents($d . '/index.html', '');
        }
        return $d;
    }

    public static function lockFile(): string
    {
        return ZL_ROOT . '/data/updating.lock';
    }

    /** True while files are being replaced (visitors see the maintenance page). Stale locks (10+ minutes) are ignored. */
    public static function running(): bool
    {
        $f = self::lockFile();
        return is_file($f) && filemtime($f) > time() - 600;
    }

    public static function current(): string
    {
        return (string) setting('version', Installer::VERSION);
    }

    /* ---------- 1. upload and check ---------- */

    public static function upload(array $file): array
    {
        if (!class_exists('ZipArchive')) {
            throw new RuntimeException(t('backup.no_zip'));
        }
        if (($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK || !is_uploaded_file($file['tmp_name'])) {
            throw new RuntimeException(($file['error'] ?? 0) === UPLOAD_ERR_INI_SIZE ? t('update.too_big_server', ['mb' => (int) ini_get('upload_max_filesize')]) : t('upload.failed'));
        }
        if ($file['size'] > self::MAX_ZIP) {
            throw new RuntimeException(t('ext.too_big'));
        }
        self::clearPending();
        $dest = self::dir() . '/pending-' . bin2hex(random_bytes(8)) . '.zip';
        if (!move_uploaded_file($file['tmp_name'], $dest)) {
            throw new RuntimeException(t('upload.failed'));
        }
        try {
            $info = self::inspect($dest);
        } catch (Throwable $e) {
            @unlink($dest);
            throw $e;
        }
        file_put_contents(self::dir() . '/pending.json', json_encode($info + ['file' => basename($dest), 'uploaded_at' => time()], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
        return $info;
    }

    /** Check a package and describe it. Throws with a clear message when it is not a usable Zinkly Social Lite package. */
    public static function inspect(string $zipFile): array
    {
        $zip = new ZipArchive();
        if ($zip->open($zipFile) !== true) {
            throw new RuntimeException(t('update.bad_zip'));
        }
        try {
            // the program can be at the top of the zip or inside one folder (zinkly-lite/)
            $prefix = null;
            for ($i = 0; $i < $zip->numFiles; $i++) {
                $n = (string) $zip->getNameIndex($i);
                if (preg_match('~^(([^/]+/)?)app/core/Installer\.php$~', $n, $m)) {
                    $prefix = $m[1];
                    break;
                }
            }
            if ($prefix === null || $zip->locateName($prefix . 'index.php') === false || $zip->locateName($prefix . 'app/bootstrap.php') === false) {
                throw new RuntimeException(t('update.not_zinkly'));
            }
            if (!preg_match("/const VERSION = '(\\d+\\.\\d+\\.\\d+)'/", (string) $zip->getFromName($prefix . 'app/core/Installer.php'), $vm)) {
                throw new RuntimeException(t('update.no_version'));
            }
            $version = $vm[1];
            $files = [];
            $changed = $added = 0;
            $total = 0;
            for ($i = 0; $i < $zip->numFiles; $i++) {
                $st = $zip->statIndex($i);
                $n = (string) $st['name'];
                if (!str_starts_with($n, $prefix) || str_ends_with($n, '/')) {
                    continue;
                }
                $rel = substr($n, strlen($prefix));
                if (!Backup::safeRel($rel)) {
                    throw new RuntimeException(t('update.bad_zip'));
                }
                if (!self::updatable($rel)) {
                    continue;
                }
                $total += (int) $st['size'];
                $files[] = $rel;
                $cur = ZL_ROOT . '/' . $rel;
                if (!is_file($cur)) {
                    $added++;
                } elseif (hash_file('crc32b', $cur) !== sprintf('%08x', $st['crc'] & 0xFFFFFFFF)) {
                    $changed++;
                }
            }
            if (count($files) > 5000 || $total > 4 * self::MAX_ZIP) {
                throw new RuntimeException(t('ext.too_big'));
            }
            $php = '8.1.0';
            $changelog = (string) $zip->getFromName($prefix . 'CHANGELOG.md');
        } finally {
            $zip->close();
        }
        $cmp = version_compare($version, self::current());
        return [
            'version' => $version, 'prefix' => $prefix, 'files' => count($files), 'changed' => $changed, 'added' => $added, 'bytes' => $total,
            'newer' => $cmp > 0, 'same' => $cmp === 0, 'older' => $cmp < 0, 'php_ok' => version_compare(PHP_VERSION, $php, '>='),
            'changelog' => self::changelogSince($changelog, self::current()),
        ];
    }

    /** Files an update may write: everything except the site's own data, photos and installed extensions. */
    private static function updatable(string $rel): bool
    {
        foreach (self::KEEP as $keep) {
            if (str_starts_with($rel, $keep)) {
                return in_array($rel, ['data/.htaccess', 'data/index.html', 'uploads/.htaccess', 'uploads/index.html', 'extensions/.htaccess', 'extensions/index.html'], true);
            }
        }
        return !in_array($rel, ['.git', '.DS_Store'], true) && !str_starts_with($rel, '.git/');
    }

    public static function pending(): ?array
    {
        $f = self::dir() . '/pending.json';
        $p = is_file($f) ? json_decode((string) file_get_contents($f), true) : null;
        if (!is_array($p) || !is_file(self::dir() . '/' . basename((string) ($p['file'] ?? '')))) {
            return null;
        }
        // the installed version may have changed since the upload
        $cmp = version_compare($p['version'], self::current());
        $p['newer'] = $cmp > 0;
        $p['same'] = $cmp === 0;
        $p['older'] = $cmp < 0;
        return $p;
    }

    public static function clearPending(): void
    {
        foreach (glob(self::dir() . '/pending*') ?: [] as $f) {
            @unlink($f);
        }
    }

    /* ---------- 3. apply ---------- */

    /** Back up, copy the new files, keep a record. The database is upgraded on the next page view (by the new code). */
    public static function apply(bool $allowSame = false): array
    {
        $p = self::pending();
        if (!$p) {
            throw new RuntimeException(t('update.none_pending'));
        }
        if ($p['older'] || ($p['same'] && !$allowSame)) {
            throw new RuntimeException($p['older'] ? t('update.older', ['v' => $p['version']]) : t('update.same'));
        }
        if (!$p['php_ok']) {
            throw new RuntimeException(t('update.php_old'));
        }
        @set_time_limit(0);
        ignore_user_abort(true);
        $from = self::current();
        $backup = Backup::create(['database', 'files'], 'before-update');
        file_put_contents(self::lockFile(), (string) time());
        $zip = new ZipArchive();
        $opened = false;
        try {
            if ($zip->open(self::dir() . '/' . basename($p['file'])) !== true) {
                throw new RuntimeException(t('update.bad_zip'));
            }
            $opened = true;
            // write the new files; anything outside the allowed paths is skipped
            $n = 0;
            for ($i = 0; $i < $zip->numFiles; $i++) {
                $entry = (string) $zip->getNameIndex($i);
                if (!str_starts_with($entry, $p['prefix']) || str_ends_with($entry, '/')) {
                    continue;
                }
                $rel = substr($entry, strlen($p['prefix']));
                if (Backup::safeRel($rel) && self::updatable($rel)) {
                    $n++;
                }
            }
            $written = self::copyFiles($zip, $p['prefix']);
            if ($written !== $n) {
                throw new RuntimeException(t('update.copy_failed', ['file' => '?']));
            }
        } catch (Throwable $e) {
            // put the program files back exactly as they were
            $restore = new ZipArchive();
            if ($restore->open(Backup::path($backup)) === true) {
                try {
                    Backup::extract($restore, 'files/', ZL_ROOT, ['data/config.php', 'data/installed.lock']);
                } catch (Throwable $e2) {
                }
                $restore->close();
            }
            @unlink(self::lockFile());
            throw new RuntimeException(t('update.failed_restored', ['error' => $e->getMessage()]));
        } finally {
            if ($opened) {
                $zip->close();
            }
        }
        if (function_exists('opcache_reset')) {
            @opcache_reset();
        }
        $record = ['from' => $from, 'to' => $p['version'], 'backup' => $backup, 'files' => $written, 'at' => time(), 'by' => Auth::id()];
        file_put_contents(self::dir() . '/last.json', json_encode($record));
        self::clearPending();
        @unlink(self::lockFile());
        return $record;
    }

    private static function copyFiles(ZipArchive $zip, string $prefix): int
    {
        $n = 0;
        for ($i = 0; $i < $zip->numFiles; $i++) {
            $entry = (string) $zip->getNameIndex($i);
            if (!str_starts_with($entry, $prefix) || str_ends_with($entry, '/')) {
                continue;
            }
            $rel = substr($entry, strlen($prefix));
            if (!Backup::safeRel($rel) || !self::updatable($rel)) {
                continue;
            }
            $target = ZL_ROOT . '/' . $rel;
            if (!is_dir(dirname($target)) && !mkdir(dirname($target), 0755, true) && !is_dir(dirname($target))) {
                throw new RuntimeException(t('update.copy_failed', ['file' => $rel]));
            }
            $in = $zip->getStream($entry);
            $tmp = $target . '.zl-new';
            $out = $in ? @fopen($tmp, 'wb') : false;
            if (!$in || !$out) {
                throw new RuntimeException(t('update.copy_failed', ['file' => $rel]));
            }
            stream_copy_to_stream($in, $out);
            fclose($in);
            fclose($out);
            if (!@rename($tmp, $target)) {
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

    public static function last(): ?array
    {
        $f = self::dir() . '/last.json';
        $r = is_file($f) ? json_decode((string) file_get_contents($f), true) : null;
        return is_array($r) ? $r : null;
    }

    /* ---------- changelog ---------- */

    /** The installed CHANGELOG.md. */
    public static function changelog(): string
    {
        $f = ZL_ROOT . '/CHANGELOG.md';
        return is_file($f) ? (string) file_get_contents($f) : '';
    }

    /** Only the sections of versions newer than $since ("## 1.2.0 - date" headings). */
    public static function changelogSince(string $md, string $since): string
    {
        $parts = preg_split('/^(?=## )/m', $md) ?: [];
        $out = '';
        foreach ($parts as $part) {
            if (preg_match('/^## \[?(\d+\.\d+\.\d+)/', $part, $m)) {
                if (version_compare($m[1], $since, '>')) {
                    $out .= $part;
                }
            }
        }
        return trim($out);
    }

    /** A small, safe Markdown renderer for changelogs: ## / ### headings, - lists, **bold**, `code`. Everything is escaped first. */
    public static function renderMarkdown(string $md): string
    {
        $html = '';
        $inList = false;
        foreach (preg_split('/\r?\n/', $md) ?: [] as $line) {
            $line = rtrim($line);
            $inline = function (string $s): string {
                $s = e($s);
                $s = preg_replace('/\*\*(.+?)\*\*/', '<strong>$1</strong>', $s) ?? $s;
                return preg_replace('/`([^`]+)`/', '<code>$1</code>', $s) ?? $s;
            };
            if (preg_match('/^\s*[-*] (.+)$/', $line, $m)) {
                if (!$inList) {
                    $html .= '<ul>';
                    $inList = true;
                }
                $html .= '<li>' . $inline($m[1]) . '</li>';
                continue;
            }
            if ($inList) {
                $html .= '</ul>';
                $inList = false;
            }
            if (preg_match('/^(#{1,3}) (.+)$/', $line, $m)) {
                $lvl = max(2, strlen($m[1]));
                $html .= '<h' . $lvl . '>' . $inline($m[2]) . '</h' . $lvl . '>';
            } elseif ($line !== '') {
                $html .= '<p>' . $inline($line) . '</p>';
            }
        }
        return $html . ($inList ? '</ul>' : '');
    }
}
