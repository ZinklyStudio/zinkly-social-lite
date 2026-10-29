<?php
/**
 * Zinkly Social Lite - extensions. An extension is a folder in extensions/<id>/ with an extension.json manifest and a PHP file that
 * registers hooks:  Ext::on('aside', fn () => '<section class="zl-card">...</section>');
 * Admins upload extensions as .zip files in Admin > Extensions, then switch them on. See EXTENSIONS.md.
 */
final class Ext
{
    public const ID_RE = '/^[a-z0-9][a-z0-9_-]{1,39}$/';
    public const MAX_ZIP = 50 * 1024 * 1024;

    /** Extensions shown in Admin > Extensions even before they are uploaded (bought separately). */
    public const FEATURED = [
        'echoai' => ['name' => 'EchoAI', 'icon' => 'sparkle'],
    ];

    private static array $hooks = [];
    private static array $active = [];
    private static ?string $current = null;

    public static function dir(string $id = ''): string
    {
        return ZL_ROOT . '/extensions' . ($id !== '' ? '/' . $id : '');
    }

    /** Load the switched-on extensions. A broken extension is switched off instead of breaking the site. */
    public static function boot(): void
    {
        if (setting('extensions_enabled', '1') !== '1') {
            return;
        }
        try {
            $rows = DB::all('SELECT * FROM {extensions} WHERE enabled = 1 ORDER BY id');
        } catch (Throwable $e) {
            return;
        }
        foreach ($rows as $row) {
            $id = (string) $row['ext_id'];
            $m = self::manifest($id);
            if (!$m) {
                continue;
            }
            $file = self::dir($id) . '/' . $m['main'];
            if (!is_file($file)) {
                continue;
            }
            self::$active[$id] = $m + ['row' => $row];
            self::$current = $id;
            try {
                (static function (string $__file) {
                    include $__file;
                })($file);
            } catch (Throwable $e) {
                self::fail($id, $e);
            }
            self::$current = null;
        }
    }

    /** Register a hook. Inside an extension file the hook remembers which extension added it. */
    public static function on(string $hook, callable $fn, int $priority = 10): void
    {
        self::$hooks[$hook][$priority][] = [$fn, self::$current];
        ksort(self::$hooks[$hook]);
    }

    public static function has(string $hook): bool
    {
        return !empty(self::$hooks[$hook]);
    }

    private static function each(string $hook): array
    {
        $out = [];
        foreach (self::$hooks[$hook] ?? [] as $list) {
            foreach ($list as $h) {
                $out[] = $h;
            }
        }
        return $out;
    }

    /** Run an action hook: Ext::fire('post_created', $postId, $userId). */
    public static function fire(string $hook, ...$args): void
    {
        foreach (self::each($hook) as [$fn, $id]) {
            try {
                $fn(...$args);
            } catch (Throwable $e) {
                self::log($id, $e);
            }
        }
    }

    /** Run a filter hook: each extension gets the value and returns it (changed or not). */
    public static function filter(string $hook, $value, ...$args)
    {
        foreach (self::each($hook) as [$fn, $id]) {
            try {
                $r = $fn($value, ...$args);
                if ($r !== null) {
                    $value = $r;
                }
            } catch (Throwable $e) {
                self::log($id, $e);
            }
        }
        return $value;
    }

    /** Collect HTML from every extension for a place in the page: <?= Ext::html('aside') ?> */
    public static function html(string $hook, ...$args): string
    {
        $out = '';
        foreach (self::each($hook) as [$fn, $id]) {
            try {
                $r = $fn(...$args);
                if (is_string($r)) {
                    $out .= $r;
                }
            } catch (Throwable $e) {
                self::log($id, $e);
            }
        }
        return $out;
    }

    /* ---------- information for extensions ---------- */

    public static function active(string $id): bool
    {
        return isset(self::$active[$id]);
    }

    public static function row(string $id): ?array
    {
        return preg_match(self::ID_RE, $id) ? DB::one('SELECT * FROM {extensions} WHERE ext_id = ?', [$id]) : null;
    }

    /** Settings saved in Admin > Extensions (fields declared in extension.json). */
    public static function settings(string $id): array
    {
        $row = self::$active[$id]['row'] ?? self::row($id);
        $s = $row ? json_decode((string) $row['settings'], true) : null;
        return is_array($s) ? $s : [];
    }

    public static function setting(string $id, string $key, $default = null)
    {
        return self::settings($id)[$key] ?? $default;
    }

    /** The license / purchase key the admin entered (for paid extensions such as EchoAI). The extension checks it with its seller. */
    public static function license(string $id): ?string
    {
        $row = self::$active[$id]['row'] ?? self::row($id);
        return $row && (string) $row['license_key'] !== '' ? (string) $row['license_key'] : null;
    }

    /** URL of a file inside the extension folder (css, js, images): Ext::asset('echoai', 'assets/echo.js'). */
    public static function asset(string $id, string $path): string
    {
        return asset('extensions/' . $id . '/' . ltrim(str_replace('..', '', $path), '/'));
    }

    /* ---------- manifest ---------- */

    /** Read and check extensions/<id>/extension.json. */
    public static function manifest(string $id): ?array
    {
        if (!preg_match(self::ID_RE, $id)) {
            return null;
        }
        $f = self::dir($id) . '/extension.json';
        $m = is_file($f) ? json_decode((string) file_get_contents($f), true) : null;
        return is_array($m) ? self::normalise($m) : null;
    }

    private static function normalise(array $m): ?array
    {
        $id = (string) ($m['id'] ?? '');
        $main = (string) ($m['main'] ?? 'extension.php');
        if (!preg_match(self::ID_RE, $id) || !preg_match('~^[A-Za-z0-9_\-/]+\.php$~', $main) || str_contains($main, '..')) {
            return null;
        }
        $lic = $m['license'] ?? false;
        $fields = [];
        foreach ((array) ($m['settings'] ?? []) as $f) {
            if (is_array($f) && preg_match('/^[a-z0-9_]{1,40}$/', (string) ($f['key'] ?? ''))) {
                $fields[] = [
                    'key' => $f['key'], 'label' => (string) ($f['label'] ?? $f['key']), 'hint' => (string) ($f['hint'] ?? ''),
                    'type' => in_array($f['type'] ?? 'text', ['text', 'password', 'textarea', 'toggle', 'select', 'number', 'url', 'email'], true) ? ($f['type'] ?? 'text') : 'text',
                    'options' => is_array($f['options'] ?? null) ? $f['options'] : [], 'default' => $f['default'] ?? '',
                ];
            }
        }
        return [
            'id' => $id, 'name' => mb_substr((string) ($m['name'] ?? $id), 0, 120), 'version' => mb_substr((string) ($m['version'] ?? '1.0.0'), 0, 20),
            'description' => mb_substr((string) ($m['description'] ?? ''), 0, 500), 'author' => mb_substr((string) ($m['author'] ?? ''), 0, 120),
            'url' => filter_var($m['url'] ?? '', FILTER_VALIDATE_URL) && preg_match('~^https?://~', (string) $m['url']) ? (string) $m['url'] : '',
            'main' => $main, 'requires' => (string) ($m['requires'] ?? '1.0.0'),
            'license' => (bool) (is_array($lic) ? ($lic['required'] ?? true) : $lic),
            'license_label' => is_array($lic) ? (string) ($lic['label'] ?? '') : '',
            'license_url' => is_array($lic) && filter_var($lic['url'] ?? '', FILTER_VALIDATE_URL) && preg_match('~^https?://~', (string) $lic['url']) ? (string) $lic['url'] : '',
            'settings' => $fields,
        ];
    }

    /** All installed extensions with their manifest. Folders copied into extensions/ by FTP are added (switched off). */
    public static function all(): array
    {
        foreach (glob(self::dir() . '/*/extension.json') ?: [] as $f) {
            $m = self::manifest(basename(dirname($f)));
            if ($m && $m['id'] === basename(dirname($f)) && !self::row($m['id'])) {
                DB::insert('extensions', ['ext_id' => $m['id'], 'name' => $m['name'], 'version' => $m['version'], 'enabled' => 0, 'settings' => '{}', 'installed_at' => time()]);
            }
        }
        $out = [];
        foreach (DB::all('SELECT * FROM {extensions} ORDER BY name') as $row) {
            $m = self::manifest((string) $row['ext_id']);
            $out[] = ['row' => $row, 'manifest' => $m, 'broken' => $m === null];
        }
        return $out;
    }

    /* ---------- install, switch, remove (Admin > Extensions) ---------- */

    /** Install or update an extension from an uploaded .zip. Returns the extension id. */
    public static function install(array $file): string
    {
        if (!class_exists('ZipArchive')) {
            throw new RuntimeException(t('ext.no_zip'));
        }
        if (($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK || !is_uploaded_file($file['tmp_name'])) {
            throw new RuntimeException(t('upload.failed'));
        }
        if ($file['size'] > self::MAX_ZIP) {
            throw new RuntimeException(t('ext.too_big'));
        }
        $zip = new ZipArchive();
        if ($zip->open($file['tmp_name']) !== true) {
            throw new RuntimeException(t('ext.bad_zip'));
        }
        try {
            // extension.json at the top of the zip, or inside one folder
            $prefix = null;
            for ($i = 0; $i < $zip->numFiles; $i++) {
                $n = (string) $zip->getNameIndex($i);
                if ($n === 'extension.json' || preg_match('~^[^/]+/extension\.json$~', $n)) {
                    if ($prefix === null || strlen($n) < strlen($prefix . 'extension.json')) {
                        $prefix = substr($n, 0, -strlen('extension.json'));
                    }
                }
            }
            if ($prefix === null) {
                throw new RuntimeException(t('ext.no_manifest'));
            }
            $m = json_decode((string) $zip->getFromName($prefix . 'extension.json'), true);
            $m = is_array($m) ? self::normalise($m) : null;
            if (!$m) {
                throw new RuntimeException(t('ext.bad_manifest'));
            }
            if (version_compare(Installer::VERSION, $m['requires'], '<')) {
                throw new RuntimeException(t('ext.needs_version', ['v' => $m['requires']]));
            }
            $total = 0;
            $files = [];
            for ($i = 0; $i < $zip->numFiles; $i++) {
                $st = $zip->statIndex($i);
                $n = (string) $st['name'];
                if (!str_starts_with($n, $prefix) || str_ends_with($n, '/')) {
                    continue;
                }
                $rel = substr($n, strlen($prefix));
                if ($rel === '' || str_contains($rel, '\\') || str_contains($rel, ':') || str_starts_with($rel, '/') || in_array('..', explode('/', $rel), true)) {
                    throw new RuntimeException(t('ext.bad_zip'));
                }
                if (preg_match('~(^|/)\.~', $rel)) {
                    continue; // hidden files (.htaccess, .git ...) are never installed
                }
                $total += (int) $st['size'];
                $files[$i] = $rel;
            }
            if (count($files) > 3000 || $total > 4 * self::MAX_ZIP) {
                throw new RuntimeException(t('ext.too_big'));
            }
            if (!in_array($m['main'], $files, true)) {
                throw new RuntimeException(t('ext.no_main', ['file' => $m['main']]));
            }

            $id = $m['id'];
            if (!is_dir(self::dir()) && !mkdir(self::dir(), 0755, true) && !is_dir(self::dir())) {
                throw new RuntimeException(t('ext.not_writable'));
            }
            $tmp = self::dir('.tmp-' . bin2hex(random_bytes(6)));
            foreach ($files as $i => $rel) {
                $dest = $tmp . '/' . $rel;
                if (!is_dir(dirname($dest)) && !mkdir(dirname($dest), 0755, true) && !is_dir(dirname($dest))) {
                    self::rmdir($tmp);
                    throw new RuntimeException(t('ext.not_writable'));
                }
                $in = $zip->getStream($zip->getNameIndex($i));
                $out = $in ? fopen($dest, 'wb') : false;
                if (!$in || !$out) {
                    self::rmdir($tmp);
                    throw new RuntimeException(t('ext.not_writable'));
                }
                stream_copy_to_stream($in, $out);
                fclose($in);
                fclose($out);
                @chmod($dest, 0644);
            }
        } finally {
            $zip->close();
        }

        $old = null;
        if (is_dir(self::dir($id))) {
            $old = self::dir('.old-' . $id . '-' . bin2hex(random_bytes(4)));
            rename(self::dir($id), $old);
        }
        if (!rename($tmp, self::dir($id))) {
            if ($old) {
                rename($old, self::dir($id));
            }
            self::rmdir($tmp);
            throw new RuntimeException(t('ext.not_writable'));
        }
        if ($old) {
            self::rmdir($old);
        }
        if (self::row($id)) {
            DB::update('extensions', ['name' => $m['name'], 'version' => $m['version']], 'ext_id = ?', [$id]);
        } else {
            DB::insert('extensions', ['ext_id' => $id, 'name' => $m['name'], 'version' => $m['version'], 'enabled' => 0, 'settings' => '{}', 'installed_at' => time()]);
        }
        return $id;
    }

    public static function enable(string $id, bool $on): void
    {
        $m = self::manifest($id);
        if ($on && !$m) {
            throw new RuntimeException(t('ext.bad_manifest'));
        }
        if ($on && $m['license'] && !self::license($id)) {
            throw new RuntimeException(t('ext.needs_license', ['name' => $m['name']]));
        }
        if ($on) {
            // load it once now, so a broken file is reported here and not on the next page
            $before = self::$current;
            self::$current = $id;
            try {
                (static function (string $__file) {
                    include_once $__file;
                })(self::dir($id) . '/' . $m['main']);
            } catch (Throwable $e) {
                self::log($id, $e);
                throw new RuntimeException(t('ext.load_failed', ['error' => $e->getMessage()]));
            } finally {
                self::$current = $before;
            }
        }
        DB::update('extensions', ['enabled' => $on ? 1 : 0], 'ext_id = ?', [$id]);
        $on ? self::fire('extension_enabled', $id) : self::fire('extension_disabled', $id);
    }

    public static function saveSettings(string $id, array $input, ?string $license): void
    {
        $m = self::manifest($id);
        if (!$m) {
            throw new RuntimeException(t('ext.bad_manifest'));
        }
        $old = self::settings($id);
        $data = [];
        foreach ($m['settings'] as $f) {
            $v = $input[$f['key']] ?? null;
            $data[$f['key']] = match ($f['type']) {
                'toggle' => $v ? '1' : '0',
                'number' => (string) (is_numeric($v) ? 0 + $v : ($f['default'] ?? 0)),
                'select' => array_key_exists((string) $v, $f['options']) || in_array((string) $v, $f['options'], true) ? (string) $v : (string) ($f['default'] ?? ''),
                // an empty password field keeps the saved value
                'password' => ($v === null || $v === '') ? (string) ($old[$f['key']] ?? '') : Text::clean((string) $v, 2000),
                'textarea' => Text::clean((string) $v, 20000),
                default => Text::clean((string) $v, 2000),
            };
        }
        $upd = ['settings' => json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)];
        if ($license !== null) {
            $upd['license_key'] = trim($license) === '' ? null : mb_substr(trim($license), 0, 190);
        }
        DB::update('extensions', $upd, 'ext_id = ?', [$id]);
        self::fire('extension_settings_saved', $id, $data);
    }

    public static function remove(string $id): void
    {
        if (!preg_match(self::ID_RE, $id)) {
            return;
        }
        if (self::active($id)) {
            self::fire('extension_removed', $id);
        }
        DB::delete('extensions', 'ext_id = ?', [$id]);
        self::rmdir(self::dir($id));
    }

    /** Delete a folder inside extensions/ (never anything outside it). */
    private static function rmdir(string $path): void
    {
        $base = realpath(self::dir());
        $real = realpath($path);
        if (!$base || !$real || !str_starts_with($real, $base . DIRECTORY_SEPARATOR)) {
            return;
        }
        $it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($real, FilesystemIterator::SKIP_DOTS), RecursiveIteratorIterator::CHILD_FIRST);
        foreach ($it as $f) {
            $f->isDir() && !$f->isLink() ? @rmdir($f->getPathname()) : @unlink($f->getPathname());
        }
        @rmdir($real);
    }

    /* ---------- errors ---------- */

    private static function log(?string $id, Throwable $e): void
    {
        @file_put_contents(ZL_ROOT . '/data/error.log', date('c') . ' [extension ' . ($id ?? '?') . '] ' . $e->getMessage() . ' @ ' . $e->getFile() . ':' . $e->getLine() . "\n", FILE_APPEND | LOCK_EX);
    }

    private static function fail(string $id, Throwable $e): void
    {
        self::log($id, $e);
        unset(self::$active[$id]);
        foreach (self::$hooks as $hook => $byPrio) {
            foreach ($byPrio as $p => $list) {
                self::$hooks[$hook][$p] = array_values(array_filter($list, fn ($h) => $h[1] !== $id));
            }
        }
        try {
            DB::update('extensions', ['enabled' => 0], 'ext_id = ?', [$id]);
        } catch (Throwable $e2) {
        }
    }
}
