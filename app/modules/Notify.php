<?php
/** Zinkly Social Lite - in-app notifications (the bell). */
final class Notify
{
    public const TYPES = ['follow', 'follow_request', 'follow_accepted', 'reaction', 'comment', 'reply', 'mention', 'share', 'comment_like', 'group_join', 'group_post', 'group_post_pending', 'group_post_approved', 'page_follow'];

    /** Add a notification. Never to yourself, never between blocked members; repeats within 10 minutes are merged. */
    public static function add(int $to, int $actor, string $type, ?int $postId = null, ?int $commentId = null, ?int $groupId = null, ?int $pageId = null): void
    {
        if (!$to || $to === $actor || Users::blockedEither($to, $actor)) {
            return;
        }
        $dupe = DB::value('SELECT id FROM {notifications} WHERE user_id = ? AND actor_id = ? AND type = ? AND ' . ($postId ? 'post_id = ?' : 'post_id IS NULL') . ' AND created_at > ?',
            array_values(array_filter([$to, $actor, $type, $postId, time() - 600], fn ($v) => $v !== null)));
        if ($dupe) {
            DB::update('notifications', ['read_at' => null, 'created_at' => time(), 'comment_id' => $commentId], 'id = ?', [$dupe]);
            return;
        }
        DB::insert('notifications', ['user_id' => $to, 'actor_id' => $actor, 'type' => $type, 'post_id' => $postId, 'comment_id' => $commentId,
            'group_id' => $groupId, 'page_id' => $pageId, 'created_at' => time()]);
    }

    public static function unread(int $user): int
    {
        return (int) DB::value('SELECT COUNT(*) FROM {notifications} WHERE user_id = ? AND read_at IS NULL', [$user]);
    }

    public static function list(int $user, int $limit = 30, int $before = 0): array
    {
        $rows = DB::all('SELECT * FROM {notifications} WHERE user_id = ?' . ($before ? ' AND id < ?' : '') . ' ORDER BY id DESC LIMIT ' . (int) $limit,
            $before ? [$user, $before] : [$user]);
        $actors = Users::many(array_column($rows, 'actor_id'));
        $pages = Pages::many(array_column($rows, 'page_id'));
        $out = [];
        foreach ($rows as $r) {
            $a = $actors[(int) $r['actor_id']] ?? null;
            if (!$a) {
                continue;
            }
            $r['actor'] = $a;
            $pg = $r['page_id'] ? ($pages[(int) $r['page_id']] ?? null) : null;
            $r['text'] = t('notif.' . $r['type'], ['name' => display_name($a), 'page' => $pg['name'] ?? '']);
            $r['link'] = match ($r['type']) {
                'follow', 'follow_request', 'follow_accepted' => url('u/' . $a['username']),
                'group_join' => $r['group_id'] ? url('g/' . (int) $r['group_id']) : url('u/' . $a['username']),
                'page_follow' => $pg ? url('pages/' . $pg['slug']) : url('u/' . $a['username']),
                default => $r['post_id'] ? url('p/' . (int) $r['post_id']) . ($r['comment_id'] ? '#c' . (int) $r['comment_id'] : '') : url('u/' . $a['username']),
            };
            $out[] = $r;
        }
        return $out;
    }

    public static function markAllRead(int $user): void
    {
        DB::query('UPDATE {notifications} SET read_at = ? WHERE user_id = ? AND read_at IS NULL', [time(), $user]);
    }
}
