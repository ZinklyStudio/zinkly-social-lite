<?php
/** Zinkly Social Lite - loads config, starts the session, connects to the database, picks the language. */
declare(strict_types=1);

define('ZL_ROOT', dirname(__DIR__));
define('ZL_START', microtime(true));

foreach (['DB', 'helpers', 'Router', 'Auth', 'RateLimit', 'View', 'Lang', 'Text', 'Upload', 'Icons', 'Mailer', 'Installer', 'Ext', 'Backup', 'Updater'] as $f) {
    require ZL_ROOT . '/app/core/' . $f . '.php';
}
spl_autoload_register(function (string $class) {
    $f = ZL_ROOT . '/app/modules/' . basename(str_replace('\\', '/', $class)) . '.php';
    if (is_file($f)) {
        require $f;
    }
});

function zl_config(): ?array
{
    static $cfg = false;
    if ($cfg === false) {
        $f = ZL_ROOT . '/data/config.php';
        $cfg = is_file($f) ? (array) require $f : null;
    }
    return $cfg;
}

function zl_installed(): bool
{
    return zl_config() !== null && is_file(ZL_ROOT . '/data/installed.lock');
}

function zl_start(bool $needInstall = true): void
{
    $cfg = zl_config();
    $debug = (bool) ($cfg['debug'] ?? false);
    ini_set('display_errors', $debug ? '1' : '0');
    error_reporting(E_ALL);
    set_exception_handler(function (Throwable $e) use ($debug) {
        error_log('[Zinkly Social Lite] ' . $e->getMessage() . ' @ ' . $e->getFile() . ':' . $e->getLine());
        @file_put_contents(ZL_ROOT . '/data/error.log', date('c') . ' ' . $e->getMessage() . ' @ ' . $e->getFile() . ':' . $e->getLine() . "\n", FILE_APPEND | LOCK_EX);
        if (!headers_sent()) {
            http_response_code(500);
        }
        echo $debug ? '<pre>' . e((string) $e) . '</pre>' : '<!doctype html><meta charset="utf-8"><title>Error</title><body style="font-family:system-ui;background:#0b0b0f;color:#e4e4e7;display:grid;place-items:center;min-height:100vh;margin:0"><div style="text-align:center"><h1 style="font-size:20px">Something went wrong</h1><p style="color:#a1a1aa">Please try again in a moment.</p></div>';
    });

    if ($needInstall && !zl_installed()) {
        header('Location: ' . base_url() . '/install/');
        exit;
    }

    // security headers (also set in .htaccess when Apache allows it)
    header('X-Content-Type-Options: nosniff');
    header('X-Frame-Options: SAMEORIGIN');
    header('Referrer-Policy: strict-origin-when-cross-origin');
    header('Permissions-Policy: camera=(), microphone=(), geolocation=()');

    $https = !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off';
    session_name('zl_session');
    session_set_cookie_params([
        'lifetime' => 0,
        'path' => base_url() === '' ? '/' : base_url() . '/',
        'secure' => $https,
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
    ini_set('session.use_strict_mode', '1');
    session_start();

    if ($cfg) {
        $db = $cfg['db'];
        if (($db['driver'] ?? '') === 'sqlite' && !str_starts_with((string) $db['path'], '/') && !preg_match('~^[A-Za-z]:[\\\\/]~', (string) $db['path'])) {
            $db['path'] = ZL_ROOT . '/' . $db['path'];
        }
        DB::connect($db);
        // new files were uploaded over an older version: add the new tables and columns once
        if (zl_installed() && setting('version', '') !== Installer::VERSION) {
            Installer::upgrade();
        }
    }
    Lang::set($cfg ? Lang::detect(zl_installed() ? Auth::user() : null) : 'en');
    if ($cfg && zl_installed()) {
        Ext::boot();
    }

    // security headers that extensions may extend (for example an AI service the browser talks to)
    if (!headers_sent()) {
        header('Content-Security-Policy: ' . zl_csp());
    }

    // housekeeping without cron: about once in 200 requests
    if ($cfg && zl_installed() && random_int(1, 200) === 1) {
        try {
            Housekeeping::run();
        } catch (Throwable $e) {
        }
    }
}

/** Content-Security-Policy. Extensions can add sources with Ext::on('csp', fn (array $csp) => ...). */
function zl_csp(): string
{
    $csp = [
        'default-src' => ["'self'"], 'img-src' => ["'self'", 'data:', 'blob:'], 'style-src' => ["'self'", "'unsafe-inline'"], 'script-src' => ["'self'"],
        'connect-src' => ["'self'"], 'font-src' => ["'self'", 'data:'], 'media-src' => ["'self'", 'blob:'], 'frame-src' => ["'self'"],
        'object-src' => ["'none'"], 'base-uri' => ["'self'"], 'frame-ancestors' => ["'self'"], 'form-action' => ["'self'"],
    ];
    $csp = class_exists('Ext', false) ? Ext::filter('csp', $csp) : $csp;
    $out = [];
    foreach ($csp as $k => $v) {
        $v = array_unique(array_filter((array) $v, fn ($x) => is_string($x) && preg_match('~^[a-zA-Z0-9\'*:/._\-]+$~', $x)));
        if (preg_match('/^[a-z-]+$/', (string) $k) && $v) {
            $out[] = $k . ' ' . implode(' ', $v);
        }
    }
    return implode('; ', $out);
}
