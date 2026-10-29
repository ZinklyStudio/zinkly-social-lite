<?php
/** Zinkly Social Lite - site pages written by admins (About, Contact, FAQ ...) at /info/<slug>, with rich text. */
final class SitePages
{
    public static function find(int $id): ?array
    {
        return $id ? DB::one('SELECT * FROM {site_pages} WHERE id = ?', [$id]) : null;
    }

    public static function bySlug(string $slug): ?array
    {
        return DB::one('SELECT * FROM {site_pages} WHERE slug = ? AND published = 1', [mb_strtolower($slug)]);
    }

    public static function all(): array
    {
        return DB::all('SELECT * FROM {site_pages} ORDER BY position, title');
    }

    /** Links for the footers (cached for the request). */
    public static function footer(): array
    {
        static $rows = null;
        if ($rows === null) {
            try {
                $rows = DB::all('SELECT slug, title FROM {site_pages} WHERE published = 1 AND in_footer = 1 ORDER BY position, title LIMIT 12');
            } catch (Throwable $e) {
                $rows = [];
            }
        }
        return $rows;
    }

    /** Create (id 0) or update. Returns the id. */
    public static function save(int $id, array $in): int
    {
        $title = Text::clean((string) ($in['title'] ?? ''), 120);
        if (mb_strlen($title) < 2) {
            throw new RuntimeException(t('sitepages.title_short'));
        }
        $slug = slugify((string) ($in['slug'] ?? '') ?: $title);
        $slug = $slug === 'group' ? 'page' : mb_substr($slug, 0, 80);
        $base = $slug;
        $i = 2;
        while (DB::value('SELECT id FROM {site_pages} WHERE slug = ? AND id <> ?', [$slug, $id])) {
            $slug = $base . '-' . $i++;
        }
        $body = Text::richAvailable() ? Text::sanitize((string) ($in['body'] ?? '')) : nl2br(e(Text::clean((string) ($in['body'] ?? ''), 60000)), false);
        $data = ['slug' => $slug, 'title' => $title, 'body' => mb_substr($body, 0, 200000), 'in_footer' => empty($in['in_footer']) ? 0 : 1,
            'published' => empty($in['published']) ? 0 : 1, 'position' => max(0, min(999, (int) ($in['position'] ?? 0))), 'updated_at' => time()];
        if ($id && self::find($id)) {
            DB::update('site_pages', $data, 'id = ?', [$id]);
            return $id;
        }
        return DB::insert('site_pages', $data + ['created_at' => time()]);
    }

    public static function delete(int $id): void
    {
        DB::delete('site_pages', 'id = ?', [$id]);
    }
}
