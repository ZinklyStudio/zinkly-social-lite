<?php
/** Zinkly Social Lite - sign-in history (IP address and device) and "sign out everywhere", for members' safety and for admins. */
final class Security
{
    /** Remember one sign-in attempt. $method: password, remember, register. */
    public static function log(int $userId, string $method, bool $ok): void
    {
        try {
            $agent = mb_substr((string) ($_SERVER['HTTP_USER_AGENT'] ?? ''), 0, 255);
            DB::insert('login_log', ['user_id' => $userId, 'ip' => RateLimit::ip(), 'agent' => $agent, 'device' => self::device($agent),
                'method' => $method, 'ok' => $ok ? 1 : 0, 'created_at' => time()]);
        } catch (Throwable $e) {
            // never block a sign-in because the history could not be written
        }
    }

    /** "Chrome on Windows", "Safari on iPhone" ... from the browser's user agent. */
    public static function device(string $ua): string
    {
        if ($ua === '') {
            return '—';
        }
        $browser = match (true) {
            str_contains($ua, 'Edg/') => 'Edge', str_contains($ua, 'OPR/') || str_contains($ua, 'Opera') => 'Opera',
            str_contains($ua, 'SamsungBrowser') => 'Samsung Internet', str_contains($ua, 'Firefox/') || str_contains($ua, 'FxiOS') => 'Firefox',
            str_contains($ua, 'Chrome/') || str_contains($ua, 'CriOS') => 'Chrome', str_contains($ua, 'Safari/') => 'Safari',
            (bool) preg_match('/bot|crawl|spider|curl|wget/i', $ua) => 'Bot', default => 'Browser',
        };
        $os = match (true) {
            str_contains($ua, 'iPhone') => 'iPhone', str_contains($ua, 'iPad') => 'iPad', str_contains($ua, 'Android') => 'Android',
            str_contains($ua, 'Windows') => 'Windows', str_contains($ua, 'Mac OS X') || str_contains($ua, 'Macintosh') => 'macOS',
            str_contains($ua, 'CrOS') => 'ChromeOS', str_contains($ua, 'Linux') => 'Linux', default => '',
        };
        return $os !== '' ? $browser . ' · ' . $os : $browser;
    }

    public static function history(int $userId, int $limit = 50): array
    {
        return DB::all('SELECT * FROM {login_log} WHERE user_id = ? ORDER BY id DESC LIMIT ' . (int) $limit, [$userId]);
    }

    /** Each IP + device the member signed in from, with the last time and how many times. */
    public static function devices(int $userId): array
    {
        return DB::all('SELECT ip, device, MAX(created_at) AS last_at, COUNT(*) AS n FROM {login_log} WHERE user_id = ? AND ok = 1 GROUP BY ip, device ORDER BY last_at DESC LIMIT 30', [$userId]);
    }

    /** Other members who signed in from the same IP addresses (shared or second accounts). */
    public static function sameIp(int $userId): array
    {
        $ips = array_column(DB::all('SELECT DISTINCT ip FROM {login_log} WHERE user_id = ? AND ok = 1 LIMIT 50', [$userId]), 'ip');
        if (!$ips) {
            return [];
        }
        return DB::all('SELECT u.*, l.ip FROM {login_log} l JOIN {users} u ON u.id = l.user_id WHERE l.ok = 1 AND l.user_id <> ? AND l.ip IN (' . DB::in($ips) . ') GROUP BY u.id, l.ip LIMIT 20',
            array_merge([$userId], $ips));
    }

    /** End every session and "keep me logged in" cookie of a member. */
    public static function signOutEverywhere(int $userId): void
    {
        DB::query('UPDATE {users} SET session_version = session_version + 1, remember_token = NULL WHERE id = ?', [$userId]);
        if ($userId === Auth::id()) {
            // keep the admin who did it signed in on this device
            $_SESSION['sv'] = (int) DB::value('SELECT session_version FROM {users} WHERE id = ?', [$userId]);
        }
    }
}
