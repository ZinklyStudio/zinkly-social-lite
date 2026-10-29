<?php
/** Zinkly Social Lite - simple fixed-window rate limits stored in the database (no cache server needed). */
final class RateLimit
{
    /** True when the action is allowed; counts the attempt. */
    public static function hit(string $key, int $max, int $seconds): bool
    {
        $key = substr($key, 0, 120);
        $now = time();
        $row = DB::one('SELECT hits, reset_at FROM {rate_limits} WHERE rkey = ?', [$key]);
        if (!$row || $row['reset_at'] <= $now) {
            DB::delete('rate_limits', 'rkey = ?', [$key]);
            DB::insertIgnore('rate_limits', ['rkey' => $key, 'hits' => 1, 'reset_at' => $now + $seconds]);
            return true;
        }
        if ($row['hits'] >= $max) {
            return false;
        }
        DB::query('UPDATE {rate_limits} SET hits = hits + 1 WHERE rkey = ?', [$key]);
        return true;
    }

    public static function clear(string $key): void
    {
        DB::delete('rate_limits', 'rkey = ?', [substr($key, 0, 120)]);
    }

    public static function ip(): string
    {
        return substr((string) ($_SERVER['REMOTE_ADDR'] ?? '0.0.0.0'), 0, 45);
    }

    /** Stop with 429 when over the limit. */
    public static function guard(string $key, int $max, int $seconds): void
    {
        if (!self::hit($key, $max, $seconds)) {
            abort(429, t('error.429'));
        }
    }
}
