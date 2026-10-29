<?php
/** Zinkly Social Lite - reports from members, reviewed in Admin > Reports. */
final class Reports
{
    public const REASONS = ['spam', 'harassment', 'hate', 'violence', 'nudity', 'false_info', 'other'];
    public const TARGETS = ['post', 'comment', 'user', 'group', 'page'];

    public static function add(int $reporter, string $type, int $id, string $reason, string $details): void
    {
        if (!in_array($type, self::TARGETS, true) || !in_array($reason, self::REASONS, true) || !$id) {
            throw new RuntimeException(t('error.422'));
        }
        if (DB::value("SELECT id FROM {reports} WHERE reporter_id = ? AND target_type = ? AND target_id = ? AND status = 'open'", [$reporter, $type, $id])) {
            return; // already reported
        }
        DB::insert('reports', ['reporter_id' => $reporter, 'target_type' => $type, 'target_id' => $id, 'reason' => $reason,
            'details' => Text::clean($details, 500), 'created_at' => time()]);
    }

    public static function open(int $limit = 50): array
    {
        return DB::all("SELECT * FROM {reports} WHERE status = 'open' ORDER BY id DESC LIMIT " . (int) $limit);
    }
}
