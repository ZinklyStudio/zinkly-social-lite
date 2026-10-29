<?php
/** Zinkly Social Lite - one-to-one messages. The page checks for new messages every few seconds (no websocket server needed). */
final class Messages
{
    public const MAX = 4000;

    /** Conversation between two members (created on first use). user_a is always the smaller id. */
    public static function conversation(int $me, int $other, bool $create = true): ?array
    {
        [$a, $b] = $me < $other ? [$me, $other] : [$other, $me];
        $c = DB::one('SELECT * FROM {conversations} WHERE user_a = ? AND user_b = ?', [$a, $b]);
        if (!$c && $create) {
            DB::insertIgnore('conversations', ['user_a' => $a, 'user_b' => $b, 'last_message_at' => 0, 'created_at' => time()]);
            $c = DB::one('SELECT * FROM {conversations} WHERE user_a = ? AND user_b = ?', [$a, $b]);
        }
        return $c;
    }

    public static function find(int $id, int $me): ?array
    {
        $c = DB::one('SELECT * FROM {conversations} WHERE id = ?', [$id]);
        return ($c && ((int) $c['user_a'] === $me || (int) $c['user_b'] === $me)) ? $c : null;
    }

    public static function otherId(array $c, int $me): int
    {
        return (int) $c['user_a'] === $me ? (int) $c['user_b'] : (int) $c['user_a'];
    }

    public static function inbox(int $me, int $limit = 50): array
    {
        $rows = DB::all('SELECT * FROM {conversations} WHERE (user_a = ? OR user_b = ?) AND last_message_at > 0 ORDER BY last_message_at DESC LIMIT ' . (int) $limit, [$me, $me]);
        $users = Users::many(array_map(fn ($c) => self::otherId($c, $me), $rows));
        $out = [];
        foreach ($rows as $c) {
            $o = $users[self::otherId($c, $me)] ?? null;
            if (!$o) {
                continue;
            }
            $last = DB::one('SELECT * FROM {messages} WHERE conversation_id = ? ORDER BY id DESC LIMIT 1', [$c['id']]);
            $c['other'] = $o;
            $c['last'] = $last;
            $c['unread'] = (int) DB::value('SELECT COUNT(*) FROM {messages} WHERE conversation_id = ? AND user_id <> ? AND read_at IS NULL', [$c['id'], $me]);
            $out[] = $c;
        }
        return $out;
    }

    public static function unreadTotal(int $me): int
    {
        return (int) DB::value('SELECT COUNT(*) FROM {messages} m JOIN {conversations} c ON c.id = m.conversation_id WHERE (c.user_a = ? OR c.user_b = ?) AND m.user_id <> ? AND m.read_at IS NULL', [$me, $me, $me]);
    }

    /** Messages after $afterId (for polling) or the last 40. */
    public static function messages(int $convId, int $afterId = 0, int $beforeId = 0): array
    {
        if ($afterId) {
            return DB::all('SELECT * FROM {messages} WHERE conversation_id = ? AND id > ? ORDER BY id ASC LIMIT 100', [$convId, $afterId]);
        }
        $rows = DB::all('SELECT * FROM {messages} WHERE conversation_id = ?' . ($beforeId ? ' AND id < ?' : '') . ' ORDER BY id DESC LIMIT 40', $beforeId ? [$convId, $beforeId] : [$convId]);
        return array_reverse($rows);
    }

    public static function send(array $c, int $me, string $body, ?array $file): array
    {
        $other = Users::find(self::otherId($c, $me));
        if (!$other || !Users::canMessage($other, $me)) {
            throw new RuntimeException(t('msg.cannot_message'));
        }
        $body = Text::clean($body, self::MAX);
        $image = null;
        if ($file) {
            $image = Upload::image($file, 'messages', 1400, null)['path'];
        }
        if ($body === '' && !$image) {
            throw new RuntimeException(t('msg.empty'));
        }
        $id = DB::insert('messages', ['conversation_id' => $c['id'], 'user_id' => $me, 'body' => $body !== '' ? $body : null, 'image' => $image, 'created_at' => time()]);
        DB::update('conversations', ['last_message_at' => time()], 'id = ?', [$c['id']]);
        return DB::one('SELECT * FROM {messages} WHERE id = ?', [$id]);
    }

    public static function markRead(array $c, int $me): void
    {
        DB::query('UPDATE {messages} SET read_at = ? WHERE conversation_id = ? AND user_id <> ? AND read_at IS NULL', [time(), $c['id'], $me]);
    }

    public static function deleteOwn(int $messageId, int $me): void
    {
        $m = DB::one('SELECT * FROM {messages} WHERE id = ? AND user_id = ?', [$messageId, $me]);
        if ($m) {
            Upload::remove($m['image']);
            DB::delete('messages', 'id = ?', [$messageId]);
        }
    }

    public static function json(array $m, int $me): array
    {
        return ['id' => (int) $m['id'], 'mine' => (int) $m['user_id'] === $me, 'html' => $m['body'] !== null ? Text::render($m['body']) : '',
            'image' => upload_url($m['image']), 'time' => date('H:i', (int) $m['created_at']), 'read' => $m['read_at'] !== null];
    }
}
