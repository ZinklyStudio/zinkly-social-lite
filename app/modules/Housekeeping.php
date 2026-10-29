<?php
/** Zinkly Social Lite - cleanup that would need cron elsewhere. Runs now and then during normal page views. */
final class Housekeeping
{
    public static function run(): void
    {
        $now = time();
        foreach (DB::all('SELECT id, path FROM {stories} WHERE expires_at < ? LIMIT 200', [$now - 3600]) as $s) {
            DB::delete('story_views', 'story_id = ?', [$s['id']]);
            DB::delete('stories', 'id = ?', [$s['id']]);
            Upload::remove($s['path']);
        }
        DB::delete('rate_limits', 'reset_at < ?', [$now - 86400]);
        DB::delete('login_log', 'created_at < ?', [$now - 180 * 86400]); // sign-in history is kept for 6 months
        DB::delete('notifications', 'created_at < ? AND read_at IS NOT NULL', [$now - 180 * 86400]);
        DB::query('UPDATE {users} SET reset_token = NULL, reset_expires = NULL WHERE reset_expires IS NOT NULL AND reset_expires < ?', [$now]);
    }
}
