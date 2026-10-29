<?php
/** Zinkly Social Lite - Pages: public pages for brands, artists, places and causes. Members follow them; page admins post as the page. */
final class Pages
{
    public const CATEGORIES = ['brand', 'business', 'local', 'artist', 'creator', 'public_figure', 'community', 'organization', 'nonprofit', 'education',
        'news', 'entertainment', 'sports', 'shop', 'restaurant', 'health', 'tech', 'other'];

    public static function enabled(): bool
    {
        return setting('pages_enabled', '1') === '1';
    }

    public static function find(int $id): ?array
    {
        return $id ? DB::one('SELECT * FROM {pages} WHERE id = ?', [$id]) : null;
    }

    public static function bySlug(string $slug): ?array
    {
        return DB::one('SELECT * FROM {pages} WHERE slug = ?', [mb_strtolower($slug)]);
    }

    public static function many(array $ids): array
    {
        $ids = array_values(array_unique(array_filter(array_map('intval', $ids))));
        $out = [];
        foreach ($ids ? DB::all('SELECT * FROM {pages} WHERE id IN (' . DB::in($ids) . ')', $ids) : [] as $p) {
            $out[(int) $p['id']] = $p;
        }
        return $out;
    }

    /** Owner or admin of the page (site admins manage every page). */
    public static function isAdmin(int $pageId, int $user): bool
    {
        if (!$user) {
            return false;
        }
        if (Auth::id() === $user && Auth::isAdmin()) {
            return true;
        }
        return (bool) DB::value('SELECT 1 FROM {page_admins} WHERE page_id = ? AND user_id = ?', [$pageId, $user]);
    }

    public static function isFollowing(int $pageId, int $user): bool
    {
        return $user && (bool) DB::value('SELECT 1 FROM {page_follows} WHERE page_id = ? AND user_id = ?', [$pageId, $user]);
    }

    public static function followedIds(int $user): array
    {
        return $user ? array_map('intval', array_column(DB::all('SELECT page_id FROM {page_follows} WHERE user_id = ?', [$user]), 'page_id')) : [];
    }

    public static function create(int $owner, string $name, string $category, string $description): int
    {
        $name = Text::clean($name, 80);
        if (mb_strlen($name) < 2) {
            throw new RuntimeException(t('pages.name_short'));
        }
        $base = slugify($name);
        $base = $base === 'group' ? 'page' : $base;
        $slug = $base;
        $i = 2;
        while (DB::value('SELECT id FROM {pages} WHERE slug = ?', [$slug])) {
            $slug = $base . '-' . $i++;
        }
        $id = DB::insert('pages', ['owner_id' => $owner, 'name' => $name, 'slug' => $slug, 'category' => in_array($category, self::CATEGORIES, true) ? $category : 'other',
            'description' => Text::clean($description, 1000), 'followers_count' => 1, 'created_at' => time()]);
        DB::insert('page_admins', ['page_id' => $id, 'user_id' => $owner, 'role' => 'owner', 'created_at' => time()]);
        DB::insert('page_follows', ['page_id' => $id, 'user_id' => $owner, 'created_at' => time()]);
        return $id;
    }

    public static function update(array $pg, array $in): void
    {
        $name = Text::clean((string) ($in['name'] ?? $pg['name']), 80);
        if (mb_strlen($name) < 2) {
            throw new RuntimeException(t('pages.name_short'));
        }
        $web = trim((string) ($in['website'] ?? ''));
        if ($web !== '' && !preg_match('~^https?://~i', $web)) {
            $web = 'https://' . $web;
        }
        DB::update('pages', [
            'name' => $name, 'category' => in_array($in['category'] ?? '', self::CATEGORIES, true) ? $in['category'] : $pg['category'],
            'description' => Text::clean((string) ($in['description'] ?? ''), 1000),
            'website' => ($web === '' || filter_var($web, FILTER_VALIDATE_URL)) ? mb_substr($web, 0, 190) : $pg['website'],
        ], 'id = ?', [$pg['id']]);
    }

    /** Follow / unfollow. Returns true when following now. */
    public static function toggleFollow(array $pg, int $user): bool
    {
        if (DB::delete('page_follows', 'page_id = ? AND user_id = ?', [$pg['id'], $user])) {
            self::recount((int) $pg['id']);
            return false;
        }
        DB::insertIgnore('page_follows', ['page_id' => $pg['id'], 'user_id' => $user, 'created_at' => time()]);
        self::recount((int) $pg['id']);
        Notify::add((int) $pg['owner_id'], $user, 'page_follow', null, null, null, (int) $pg['id']);
        return true;
    }

    public static function admins(int $pageId): array
    {
        $rows = DB::all("SELECT * FROM {page_admins} WHERE page_id = ? ORDER BY CASE role WHEN 'owner' THEN 0 ELSE 1 END, id", [$pageId]);
        $users = Users::many(array_column($rows, 'user_id'));
        $out = [];
        foreach ($rows as $r) {
            if (isset($users[(int) $r['user_id']])) {
                $out[] = ['user' => $users[(int) $r['user_id']], 'role' => $r['role']];
            }
        }
        return $out;
    }

    public static function addAdmin(array $pg, string $username): void
    {
        $u = Users::byUsername(ltrim(trim($username), '@'));
        if (!$u || $u['banned']) {
            throw new RuntimeException(t('pages.no_such_member'));
        }
        DB::insertIgnore('page_admins', ['page_id' => $pg['id'], 'user_id' => $u['id'], 'role' => 'admin', 'created_at' => time()]);
    }

    public static function removeAdmin(array $pg, int $user): void
    {
        if ((int) $pg['owner_id'] === $user) {
            return;
        }
        DB::delete('page_admins', 'page_id = ? AND user_id = ?', [$pg['id'], $user]);
    }

    public static function recount(int $id): void
    {
        DB::update('pages', [
            'followers_count' => (int) DB::value('SELECT COUNT(*) FROM {page_follows} WHERE page_id = ?', [$id]),
            'posts_count' => (int) DB::value("SELECT COUNT(*) FROM {posts} WHERE page_id = ? AND status = 'published'", [$id]),
        ], 'id = ?', [$id]);
    }

    public static function list(string $q = '', string $category = '', int $limit = 30): array
    {
        $where = '1=1';
        $params = [];
        if ($q !== '') {
            $where .= ' AND (' . DB::like('name') . ' OR ' . DB::like('description') . ')';
            $l = DB::escapeLike($q);
            array_push($params, $l, $l);
        }
        if (in_array($category, self::CATEGORIES, true)) {
            $where .= ' AND category = ?';
            $params[] = $category;
        }
        return DB::all("SELECT * FROM {pages} WHERE $where ORDER BY verified DESC, followers_count DESC, id DESC LIMIT " . (int) $limit, $params);
    }

    public static function mine(int $user): array
    {
        return DB::all('SELECT p.* FROM {pages} p JOIN {page_admins} a ON a.page_id = p.id WHERE a.user_id = ? ORDER BY p.name', [$user]);
    }

    /** Pages to follow: popular pages the member does not follow yet. */
    public static function suggestions(int $user, int $limit = 3): array
    {
        $skip = self::followedIds($user) ?: [0];
        return DB::all('SELECT * FROM {pages} WHERE id NOT IN (' . DB::in($skip) . ') ORDER BY verified DESC, followers_count DESC, id DESC LIMIT ' . (int) $limit, $skip);
    }

    public static function delete(int $id): void
    {
        $pg = self::find($id);
        if (!$pg) {
            return;
        }
        foreach (DB::all('SELECT id FROM {posts} WHERE page_id = ?', [$id]) as $p) {
            Posts::delete((int) $p['id']);
        }
        DB::delete('page_follows', 'page_id = ?', [$id]);
        DB::delete('page_admins', 'page_id = ?', [$id]);
        DB::delete('notifications', 'page_id = ?', [$id]);
        DB::delete('reports', "target_type = 'page' AND target_id = ?", [$id]);
        DB::delete('pages', 'id = ?', [$id]);
        Upload::remove($pg['avatar']);
        Upload::remove($pg['cover']);
    }

    /** Avatar for a page (photo or initials). */
    public static function avatar(array $pg, string $size = 'md'): string
    {
        if (!empty($pg['avatar'])) {
            return '<span class="zl-av zl-av-' . $size . ' zl-av-page"><img src="' . e(upload_url($pg['avatar'])) . '" alt="" loading="lazy" decoding="async"></span>';
        }
        return '<span class="zl-av zl-av-' . $size . ' zl-av-page" aria-hidden="true">' . e(initials($pg['name'])) . '</span>';
    }
}
