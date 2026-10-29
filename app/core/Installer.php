<?php
/** Zinkly Social Lite - creates the tables (MySQL / MariaDB or SQLite) and the first admin. Used by install/index.php. */
final class Installer
{
    public const VERSION = '1.0.0';

    /** [label, ok, info, required]. Missing required items block the install; the others only lose a feature. */
    public static function requirements(): array
    {
        $root = ZL_ROOT;
        $mb = (int) ini_get('upload_max_filesize');
        return [
            ['PHP 8.1 or newer', version_compare(PHP_VERSION, '8.1.0', '>='), PHP_VERSION, true],
            ['PDO', extension_loaded('pdo'), '', true],
            ['PDO MySQL or PDO SQLite', extension_loaded('pdo_mysql') || extension_loaded('pdo_sqlite'), implode(', ', array_filter([extension_loaded('pdo_mysql') ? 'MySQL' : '', extension_loaded('pdo_sqlite') ? 'SQLite' : ''])), true],
            ['GD (images)', extension_loaded('gd'), '', true],
            ['mbstring', extension_loaded('mbstring'), '', true],
            ['fileinfo', extension_loaded('fileinfo'), '', true],
            ['JSON', function_exists('json_encode'), '', true],
            ['data/ is writable', is_writable($root . '/data'), is_writable($root . '/data') ? '' : t('install.data_not_writable'), true],
            ['uploads/ is writable', is_writable($root . '/uploads'), is_writable($root . '/uploads') ? '' : t('install.uploads_not_writable'), true],
            ['dom (rich text posts)', class_exists('DOMDocument'), t('install.opt_dom'), false],
            ['zip (upload extensions)', class_exists('ZipArchive'), t('install.opt_zip'), false],
            ['extensions/ is writable', is_dir($root . '/extensions') && is_writable($root . '/extensions'), t('install.opt_ext_dir'), false],
            ['Upload limit ' . $mb . ' MB', $mb >= 8, t('install.opt_upload', ['mb' => $mb]), false],
        ];
    }

    /** Database settings from the install form (or the "Test connection" button). */
    public static function dbFromInput(array $in): array
    {
        $prefix = preg_replace('/[^a-z0-9_]/i', '', (string) ($in['prefix'] ?? 'zl_'));
        if (($in['driver'] ?? 'mysql') === 'sqlite') {
            return ['driver' => 'sqlite', 'prefix' => $prefix];
        }
        return ['driver' => 'mysql', 'host' => trim((string) ($in['db_host'] ?? 'localhost')) ?: 'localhost', 'port' => (int) ($in['db_port'] ?? 3306) ?: 3306,
            'name' => trim((string) ($in['db_name'] ?? '')), 'user' => trim((string) ($in['db_user'] ?? '')), 'pass' => (string) ($in['db_pass'] ?? ''), 'prefix' => $prefix];
    }

    /**
     * Check a database before installing: connect, server version, utf8mb4, free table prefix, and the rights the installer needs
     * (create / alter / index / insert / drop on a throw-away test table). Nothing is left behind.
     * Returns ['ok' => bool, 'message' => string, 'checks' => [[label, 'ok'|'warn'|'fail', detail], ...]].
     */
    public static function checkDatabase(array $db): array
    {
        $checks = [];
        $add = function (string $label, string $state, string $detail = '') use (&$checks) {
            $checks[] = [$label, $state, $detail];
        };
        $prefix = (string) $db['prefix'];
        if (!preg_match('/^[A-Za-z0-9_]{0,12}$/', $prefix)) {
            return ['ok' => false, 'message' => t('install.bad_prefix'), 'checks' => []];
        }
        if ($db['driver'] === 'sqlite') {
            if (!extension_loaded('pdo_sqlite')) {
                return ['ok' => false, 'message' => t('install.no_sqlite'), 'checks' => []];
            }
            if (!is_writable(ZL_ROOT . '/data')) {
                return ['ok' => false, 'message' => t('install.data_not_writable'), 'checks' => []];
            }
            $file = ZL_ROOT . '/data/.install-test-' . bin2hex(random_bytes(4)) . '.sqlite';
            try {
                $pdo = new PDO('sqlite:' . $file, null, null, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
                $v = (string) $pdo->query('SELECT sqlite_version()')->fetchColumn();
                $pdo->exec('CREATE TABLE t (id INTEGER PRIMARY KEY AUTOINCREMENT, a TEXT)');
                $pdo->exec('ALTER TABLE t ADD COLUMN b INTEGER NOT NULL DEFAULT 0');
                $pdo->exec('CREATE INDEX t_b ON t (b)');
                $pdo->exec("INSERT INTO t (a) VALUES ('Ελληνικά 😀')");
                $back = (string) $pdo->query('SELECT a FROM t')->fetchColumn();
                $pdo = null;
                $add(t('install.chk_connect'), 'ok', 'SQLite ' . $v);
                $add(t('install.chk_write'), 'ok', 'data/');
                $add(t('install.chk_unicode'), $back === 'Ελληνικά 😀' ? 'ok' : 'fail');
            } catch (Throwable $e) {
                $add(t('install.chk_connect'), 'fail', $e->getMessage());
            } finally {
                @unlink($file);
                @unlink($file . '-wal');
                @unlink($file . '-shm');
            }
            $ok = !in_array('fail', array_column($checks, 1), true);
            return ['ok' => $ok, 'message' => $ok ? t('install.db_ok_sqlite') : t('install.db_failed'), 'checks' => $checks];
        }

        if (!extension_loaded('pdo_mysql')) {
            return ['ok' => false, 'message' => t('install.no_mysql'), 'checks' => []];
        }
        if ($db['name'] === '' || $db['user'] === '') {
            return ['ok' => false, 'message' => t('install.db_required'), 'checks' => []];
        }
        if (!preg_match('/^[A-Za-z0-9_$-]{1,64}$/', $db['name'])) {
            return ['ok' => false, 'message' => t('install.bad_db_name'), 'checks' => []];
        }
        try {
            $dsn = sprintf('mysql:host=%s;port=%d;dbname=%s;charset=utf8mb4', $db['host'], $db['port'], $db['name']);
            $pdo = new PDO($dsn, $db['user'], $db['pass'], [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_TIMEOUT => 6, PDO::ATTR_EMULATE_PREPARES => false]);
        } catch (PDOException $e) {
            return ['ok' => false, 'message' => self::explain($e, $db), 'checks' => [[t('install.chk_connect'), 'fail', self::short($e)]]];
        }
        $ver = (string) $pdo->query('SELECT VERSION()')->fetchColumn();
        $maria = stripos($ver, 'mariadb') !== false;
        $num = preg_replace('/[^0-9.].*$/', '', $ver);
        $new = $maria ? version_compare($num, '10.3', '>=') : version_compare($num, '5.7', '>=');
        $add(t('install.chk_connect'), 'ok', ($maria ? 'MariaDB ' : 'MySQL ') . $num . ' · ' . $db['host'] . ':' . $db['port']);
        $add(t('install.chk_version'), $new ? 'ok' : 'warn', $new ? '' : t('install.old_server'));

        $utf = (bool) $pdo->query("SHOW CHARACTER SET LIKE 'utf8mb4'")->fetch();
        $add(t('install.chk_unicode'), $utf ? 'ok' : 'fail', $utf ? 'utf8mb4' : t('install.no_utf8mb4'));

        // none of the table names the installer will create may exist yet (a failed install removes exactly these names)
        $names = array_map(fn ($tb) => $prefix . $tb, array_keys((require ZL_ROOT . '/app/schema.php')['tables']));
        $stmt = $pdo->prepare('SELECT table_name FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name IN (' . implode(',', array_fill(0, count($names), '?')) . ')');
        $stmt->execute($names);
        $existing = $stmt->fetchAll(PDO::FETCH_COLUMN);
        $taken = (bool) $existing;
        $add(t('install.chk_prefix', ['prefix' => $prefix === '' ? '—' : $prefix]), $taken ? 'fail' : 'ok',
            $taken ? t('install.tables_exist') . ' (' . implode(', ', array_slice($existing, 0, 4)) . (count($existing) > 4 ? ' …' : '') . ')' : '');

        // the rights the installer needs, tried for real on a test table that is removed again
        $test = $prefix . 'zl_install_test';
        try {
            $pdo->exec("DROP TABLE IF EXISTS `$test`");
            $pdo->exec("CREATE TABLE `$test` (id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY, a VARCHAR(191) NOT NULL) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
            $pdo->exec("ALTER TABLE `$test` ADD COLUMN b INT NOT NULL DEFAULT 0");
            $pdo->exec("CREATE INDEX `{$test}_b` ON `$test` (b)");
            $ins = $pdo->prepare("INSERT INTO `$test` (a) VALUES (?)");
            $ins->execute(['Ελληνικά 😀']);
            $back = (string) $pdo->query("SELECT a FROM `$test`")->fetchColumn();
            $pdo->exec("DROP TABLE `$test`");
            $add(t('install.chk_rights'), 'ok', 'CREATE · ALTER · INDEX · INSERT · DROP');
            if ($utf && $back !== 'Ελληνικά 😀') {
                $add(t('install.chk_unicode'), 'fail', t('install.no_utf8mb4'));
            }
        } catch (PDOException $e) {
            try {
                $pdo->exec("DROP TABLE IF EXISTS `$test`");
            } catch (Throwable $e2) {
            }
            $add(t('install.chk_rights'), 'fail', t('install.no_rights') . ' (' . self::short($e) . ')');
        }
        $ok = !in_array('fail', array_column($checks, 1), true);
        return ['ok' => $ok, 'message' => $ok ? t('install.db_ok', ['db' => $db['name']]) : ($taken ? t('install.tables_exist') : t('install.db_problem')), 'checks' => $checks];
    }

    /** Turn a MySQL connection error into advice a site owner can act on. */
    private static function explain(PDOException $e, array $db): string
    {
        $code = (int) ($e->errorInfo[1] ?? 0);
        if (!$code && preg_match('/\[(\d{4})\]/', $e->getMessage(), $m)) {
            $code = (int) $m[1];
        }
        return match (true) {
            $code === 1045 => t('install.err_1045', ['user' => $db['user']]),
            $code === 1044 => t('install.err_1044', ['user' => $db['user'], 'db' => $db['name']]),
            $code === 1049 => t('install.err_1049', ['db' => $db['name']]),
            in_array($code, [2002, 2003, 2006], true) => t('install.err_2002', ['host' => $db['host'], 'port' => $db['port']]),
            $code === 2005 => t('install.err_2005', ['host' => $db['host']]),
            default => t('install.db_failed') . ' ' . self::short($e),
        };
    }

    private static function short(Throwable $e): string
    {
        return trim(preg_replace('/SQLSTATE\[\w+\]:?\s*(\[\d+\])?\s*/', '', $e->getMessage()) ?? $e->getMessage());
    }

    /** Remove the tables of a failed install (MySQL), so the next try starts clean. Only Zinkly Social Lite's own table names. */
    public static function dropSchema(): void
    {
        $schema = require ZL_ROOT . '/app/schema.php';
        foreach (array_keys($schema['tables']) as $name) {
            try {
                DB::pdo()->exec('DROP TABLE IF EXISTS ' . DB::t($name));
            } catch (Throwable $e) {
            }
        }
    }

    public static function createSchema(): void
    {
        $schema = require ZL_ROOT . '/app/schema.php';
        $sqlite = DB::driver() === 'sqlite';
        $map = $sqlite
            ? ['{pk}' => 'id INTEGER PRIMARY KEY AUTOINCREMENT', '{str}' => 'VARCHAR(191)', '{txt}' => 'TEXT', '{int}' => 'INTEGER', '{big}' => 'INTEGER']
            : ['{pk}' => 'id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY', '{str}' => 'VARCHAR(191)', '{txt}' => 'MEDIUMTEXT', '{int}' => 'INT', '{big}' => 'BIGINT'];
        foreach ($schema['tables'] as $name => $cols) {
            $sql = 'CREATE TABLE IF NOT EXISTS ' . DB::t($name) . ' (' . strtr($cols, $map) . ')';
            if (!$sqlite) {
                $sql .= ' ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci';
            }
            DB::pdo()->exec($sql);
        }
        foreach ($schema['columns'] ?? [] as $table => $cols) {
            $have = self::columns($table);
            foreach ($cols as $col => $def) {
                if (!in_array($col, $have, true)) {
                    DB::pdo()->exec('ALTER TABLE ' . DB::t($table) . ' ADD COLUMN ' . $col . ' ' . strtr($def, $map));
                }
            }
        }
        foreach ($schema['indexes'] as $table => $list) {
            foreach ($list as $idx) {
                $unique = str_starts_with($idx, 'UNIQUE:');
                $cols = $unique ? substr($idx, 7) : $idx;
                $iname = substr(DB::t($table) . '_' . str_replace(',', '_', $cols) . ($unique ? '_u' : '_i'), 0, 64);
                $sql = 'CREATE ' . ($unique ? 'UNIQUE ' : '') . 'INDEX ' . ($sqlite ? 'IF NOT EXISTS ' : '') . $iname . ' ON ' . DB::t($table) . ' (' . $cols . ')';
                try {
                    DB::pdo()->exec($sql);
                } catch (PDOException $e) {
                    if (!str_contains($e->getMessage(), 'Duplicate key name')) {
                        throw $e;
                    }
                }
            }
        }
    }

    /** Column names of a table. */
    private static function columns(string $table): array
    {
        if (DB::driver() === 'sqlite') {
            return array_column(DB::all('PRAGMA table_info(' . DB::t($table) . ')'), 'name');
        }
        return array_column(DB::all('SHOW COLUMNS FROM ' . DB::t($table)), 'Field');
    }

    /**
     * Bring an older install up to this version: new tables, new columns, new default settings.
     * Runs by itself on the first page view after the files were replaced (see zl_start), so no terminal is needed.
     */
    public static function upgrade(): void
    {
        $lock = ZL_ROOT . '/data/upgrade.lock';
        $fh = @fopen($lock, 'c');
        if ($fh && !flock($fh, LOCK_EX | LOCK_NB)) {
            fclose($fh);
            return; // another request is upgrading right now
        }
        try {
            self::createSchema();
            self::defaults((string) setting('site_name', 'Zinkly Social Lite'), (string) setting('default_lang', 'en'));
            setting_set('version', self::VERSION);
        } finally {
            if ($fh) {
                flock($fh, LOCK_UN);
                fclose($fh);
                @unlink($lock);
            }
        }
    }

    /** Write data/config.php. Values are exported with var_export, so nothing typed in the form can run as code. */
    public static function writeConfig(array $cfg): void
    {
        $php = "<?php\n// Zinkly Social Lite configuration (written by the installer). Keep this file private.\nreturn " . var_export($cfg, true) . ";\n";
        $file = ZL_ROOT . '/data/config.php';
        if (file_put_contents($file, $php, LOCK_EX) === false) {
            throw new RuntimeException('Could not write data/config.php');
        }
        @chmod($file, 0640);
    }

    public static function defaults(string $siteName, string $lang): void
    {
        $now = (string) time();
        foreach ([
            'site_name' => $siteName, 'site_description' => '', 'default_lang' => $lang, 'registration_open' => '1',
            'max_upload_mb' => '8', 'max_post_images' => '4', 'stories_enabled' => '1', 'groups_enabled' => '1',
            'messages_enabled' => '1', 'installed_at' => $now, 'version' => self::VERSION, 'terms_text' => '', 'privacy_text' => '',
            'mail_from' => '', 'noindex' => '0', 'mail_method' => 'php', 'smtp_port' => '587', 'smtp_encryption' => 'tls',
            // features, look and maintenance
            'pages_enabled' => '1', 'rich_text' => '1', 'anonymous_posts' => '1', 'animations' => '1', 'accent' => 'violet', 'guest_theme' => 'system',
            'landing_mode' => 'landing', 'landing_title' => '', 'landing_lead' => '', 'landing_features' => '1', 'landing_stats' => '1',
            'maintenance' => '0', 'maintenance_text' => '', 'extensions_enabled' => '1', 'comments_enabled' => '1', 'max_post_chars' => '5000',
        ] as $k => $v) {
            if (DB::value('SELECT COUNT(*) FROM {settings} WHERE skey = ?', [$k])) {
                continue;
            }
            DB::insert('settings', ['skey' => $k, 'svalue' => $v]);
        }
    }
}
