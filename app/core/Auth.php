<?php
/** Zinkly Social Lite - sign-in state, remember-me cookie, roles. */
final class Auth
{
    private static ?array $user = null;
    private static bool $loaded = false;

    public static function user(): ?array
    {
        if (!self::$loaded) {
            self::$loaded = true;
            if (!zl_installed()) {
                return null;
            }
            $id = (int) ($_SESSION['uid'] ?? 0);
            if (!$id && !empty($_COOKIE['zl_remember'])) {
                $id = self::fromRemember((string) $_COOKIE['zl_remember']);
            }
            if ($id) {
                $u = DB::one('SELECT * FROM {users} WHERE id = ?', [$id]);
                // "sign out everywhere" raises session_version, which ends every session made before it
                if ($u && (int) ($_SESSION['sv'] ?? 0) !== (int) ($u['session_version'] ?? 0)) {
                    $u = null;
                }
                if ($u && !$u['banned']) {
                    self::$user = $u;
                    if ($u['last_seen'] < time() - 60) {
                        DB::update('users', ['last_seen' => time()], 'id = ?', [$id]);
                    }
                } else {
                    self::logout();
                }
            }
        }
        return self::$user;
    }

    public static function id(): int
    {
        return (int) (self::user()['id'] ?? 0);
    }

    public static function check(): bool
    {
        return self::user() !== null;
    }

    public static function isAdmin(): bool
    {
        return (self::user()['role'] ?? '') === 'admin';
    }

    public static function isMod(): bool
    {
        return in_array(self::user()['role'] ?? '', ['admin', 'moderator'], true);
    }

    public static function login(array $u, bool $remember = false): void
    {
        session_regenerate_id(true);
        $_SESSION['uid'] = (int) $u['id'];
        $_SESSION['sv'] = (int) ($u['session_version'] ?? 0);
        $_SESSION['_csrf'] = bin2hex(random_bytes(32));
        self::$user = $u;
        self::$loaded = true;
        if ($remember) {
            $token = bin2hex(random_bytes(32));
            DB::update('users', ['remember_token' => hash('sha256', $token)], 'id = ?', [$u['id']]);
            self::cookie('zl_remember', $u['id'] . '|' . $token, time() + 60 * 60 * 24 * 60);
        }
    }

    public static function logout(): void
    {
        if (self::$user) {
            DB::update('users', ['remember_token' => null], 'id = ?', [self::$user['id']]);
        }
        self::$user = null;
        $_SESSION = [];
        if (session_status() === PHP_SESSION_ACTIVE) {
            session_regenerate_id(true);
        }
        self::cookie('zl_remember', '', time() - 3600);
    }

    private static function fromRemember(string $raw): int
    {
        [$id, $token] = array_pad(explode('|', $raw, 2), 2, '');
        $id = (int) $id;
        if (!$id || $token === '') {
            return 0;
        }
        $row = DB::one('SELECT remember_token, session_version FROM {users} WHERE id = ?', [$id]);
        $hash = $row['remember_token'] ?? null;
        if ($hash && hash_equals((string) $hash, hash('sha256', $token))) {
            session_regenerate_id(true);
            $_SESSION['uid'] = $id;
            $_SESSION['sv'] = (int) ($row['session_version'] ?? 0);
            Security::log($id, 'remember', true);
            return $id;
        }
        return 0;
    }

    private static function cookie(string $name, string $value, int $expires): void
    {
        setcookie($name, $value, [
            'expires' => $expires,
            'path' => base_url() === '' ? '/' : base_url() . '/',
            'secure' => !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off',
            'httponly' => true,
            'samesite' => 'Lax',
        ]);
    }

    /** Validate a new password: 8+ characters with a letter and a number. */
    public static function passwordError(string $p): ?string
    {
        if (mb_strlen($p) < 8) {
            return t('auth.password_short');
        }
        if (!preg_match('/\p{L}/u', $p) || !preg_match('/\d/', $p)) {
            return t('auth.password_weak');
        }
        return null;
    }
}
