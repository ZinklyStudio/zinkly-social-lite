<?php
/** Zinkly Social Lite - small helpers used everywhere. */

function e($v): string
{
    return htmlspecialchars((string) $v, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function base_path(string $p = ''): string
{
    return ZL_ROOT . ($p !== '' ? '/' . ltrim($p, '/') : '');
}

/** URL path of the install folder ('' at the domain root, '/lite' in a subfolder). */
function base_url(): string
{
    static $b = null;
    if ($b === null) {
        $b = rtrim(str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? '/index.php')), '/');
        if (substr($b, -8) === '/install') {
            $b = substr($b, 0, -8);
        }
    }
    return $b;
}

function url(string $path = '', array $query = []): string
{
    $u = base_url() . '/' . ltrim($path, '/');
    if ($query) {
        $u .= '?' . http_build_query($query);
    }
    return $u === '' ? '/' : $u;
}

function full_url(string $path = ''): string
{
    $https = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') || (($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https');
    return ($https ? 'https' : 'http') . '://' . ($_SERVER['HTTP_HOST'] ?? 'localhost') . url($path);
}

function asset(string $path): string
{
    $file = base_path($path);
    return url($path) . (is_file($file) ? '?v=' . filemtime($file) : '');
}

function upload_url(?string $path): ?string
{
    return $path ? url('uploads/' . ltrim($path, '/')) : null;
}

function redirect(string $to): never
{
    header('Location: ' . (preg_match('~^https?://~', $to) ? $to : url($to)));
    exit;
}

function back(): never
{
    $ref = $_SERVER['HTTP_REFERER'] ?? '';
    $host = $_SERVER['HTTP_HOST'] ?? '';
    if ($ref && parse_url($ref, PHP_URL_HOST) === $host) {
        header('Location: ' . $ref);
        exit;
    }
    redirect('');
}

function json_out($data, int $status = 200): never
{
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    header('Cache-Control: no-store');
    echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

function wants_json(): bool
{
    return str_contains($_SERVER['HTTP_ACCEPT'] ?? '', 'application/json') || ($_SERVER['HTTP_X_REQUESTED_WITH'] ?? '') === 'XMLHttpRequest';
}

function abort(int $code, string $msg = ''): never
{
    http_response_code($code);
    if (wants_json()) {
        json_out(['error' => $msg ?: t('error.' . $code)], $code);
    }
    View::render('pages/error', ['code' => $code, 'message' => $msg ?: t('error.' . $code), 'title' => (string) $code], Auth::check() ? 'layout' : 'layout-guest');
    exit;
}

function input(string $key, $default = null)
{
    $v = $_POST[$key] ?? $_GET[$key] ?? $default;
    return is_string($v) ? trim($v) : $v;
}

function now(): int
{
    return time();
}

/* ---------- session, flash, CSRF ---------- */

function flash(string $key, ?string $msg = null): ?string
{
    if ($msg !== null) {
        $_SESSION['_flash'][$key] = $msg;
        return null;
    }
    $m = $_SESSION['_flash'][$key] ?? null;
    unset($_SESSION['_flash'][$key]);
    return $m;
}

function old(string $key, string $default = ''): string
{
    return (string) ($_SESSION['_old'][$key] ?? $default);
}

function keep_old(array $data): void
{
    unset($data['password'], $data['password2'], $data['_token']);
    $_SESSION['_old'] = $data;
}

function csrf_token(): string
{
    if (empty($_SESSION['_csrf'])) {
        $_SESSION['_csrf'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['_csrf'];
}

function csrf_field(): string
{
    return '<input type="hidden" name="_token" value="' . e(csrf_token()) . '">';
}

function csrf_check(): void
{
    $sent = $_POST['_token'] ?? ($_SERVER['HTTP_X_CSRF_TOKEN'] ?? '');
    if (!is_string($sent) || !hash_equals(csrf_token(), $sent)) {
        abort(419);
    }
}

/* ---------- settings ---------- */

function setting(string $key, $default = null)
{
    static $cache = null;
    if ($cache === null) {
        $cache = [];
        try {
            foreach (DB::all('SELECT skey, svalue FROM {settings}') as $r) {
                $cache[$r['skey']] = $r['svalue'];
            }
        } catch (Throwable $e) {
        }
    }
    if ($key === '__flush') {
        $cache = null;
        return null;
    }
    return array_key_exists($key, $cache) && $cache[$key] !== null ? $cache[$key] : $default;
}

function setting_set(string $key, ?string $value): void
{
    if (DB::value('SELECT COUNT(*) FROM {settings} WHERE skey = ?', [$key])) {
        DB::update('settings', ['svalue' => $value], 'skey = ?', [$key]);
    } else {
        DB::insert('settings', ['skey' => $key, 'svalue' => $value]);
    }
    setting('__flush');
}

function site_name(): string
{
    return (string) setting('site_name', 'Zinkly Social Lite');
}

/* ---------- translation ---------- */

function t(string $key, array $r = []): string
{
    return Lang::get($key, $r);
}

function tc(string $key, int $n, array $r = []): string
{
    return Lang::choice($key, $n, $r);
}

/* ---------- formatting ---------- */

function ago(int $ts): string
{
    $d = max(0, time() - $ts);
    if ($d < 60) {
        return t('time.now');
    }
    if ($d < 3600) {
        return t('time.m', ['n' => intdiv($d, 60)]);
    }
    if ($d < 86400) {
        return t('time.h', ['n' => intdiv($d, 3600)]);
    }
    if ($d < 604800) {
        return t('time.d', ['n' => intdiv($d, 86400)]);
    }
    return Lang::date($ts);
}

function short_number(int $n): string
{
    if ($n < 1000) {
        return (string) $n;
    }
    if ($n < 1000000) {
        return rtrim(rtrim(number_format($n / 1000, 1), '0'), '.') . 'K';
    }
    return rtrim(rtrim(number_format($n / 1000000, 1), '0'), '.') . 'M';
}

function initials(string $name): string
{
    $parts = preg_split('/\s+/u', trim($name)) ?: [];
    $s = '';
    foreach (array_slice($parts, 0, 2) as $p) {
        $s .= mb_strtoupper(mb_substr($p, 0, 1));
    }
    return $s !== '' ? $s : '?';
}

function display_name(array $u): string
{
    return $u['name'] !== '' ? $u['name'] : $u['username'];
}

function avatar_html(array $u, string $size = 'md'): string
{
    $label = e(display_name($u));
    if (!empty($u['avatar'])) {
        return '<span class="zl-av zl-av-' . $size . '"><img src="' . e(upload_url($u['avatar'])) . '" alt="" loading="lazy" decoding="async"></span>';
    }
    return '<span class="zl-av zl-av-' . $size . '" aria-hidden="true">' . e(initials(display_name($u))) . '</span>';
}

function icon(string $name, string $class = 'zl-i'): string
{
    return Icons::svg($name, $class);
}

function str_limit(string $s, int $n): string
{
    return mb_strlen($s) > $n ? rtrim(mb_substr($s, 0, $n - 1)) . '…' : $s;
}

function slugify(string $s): string
{
    $s = mb_strtolower(trim($s));
    $s = preg_replace('/[^\p{L}\p{N}]+/u', '-', $s) ?? '';
    return trim($s, '-') ?: 'group';
}
