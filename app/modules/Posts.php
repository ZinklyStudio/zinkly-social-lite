<?php
/** Zinkly Social Lite - posts, photos, likes, comments, shares, saved posts, feeds. */
final class Posts
{
    public const PRIVACY = ['public', 'followers', 'only_me'];
    public const MAX_BODY = 5000;
    public const MAX_COMMENT = 2000;
    public const PAGE = 15;

    public const STATUSES = ['published', 'pending', 'hidden'];

    public static function find(int $id): ?array
    {
        return $id ? DB::one('SELECT * FROM {posts} WHERE id = ?', [$id]) : null;
    }

    /** Longest post text (Admin > Settings). Rich text gets room for its formatting. */
    public static function maxBody(string $format = 'text'): int
    {
        $n = max(100, min(20000, (int) setting('max_post_chars', (string) self::MAX_BODY)));
        return $format === 'html' ? $n * 4 : $n;
    }

    /* ---------- who can see what ---------- */

    public static function canView(array $p, int $viewer): bool
    {
        $owner = (int) $p['user_id'];
        if ($owner === $viewer) {
            return true;
        }
        $author = Users::find($owner);
        if (!$author || $author['banned'] || Users::blockedEither($owner, $viewer)) {
            return false;
        }
        if (Auth::isMod()) {
            return true;
        }
        $status = $p['status'] ?? 'published';
        if ($status === 'hidden') {
            return false;
        }
        if ($p['group_id']) {
            $g = Groups::find((int) $p['group_id']);
            if ($status === 'pending') {
                return $g && Groups::isManager((int) $g['id'], $viewer);
            }
            return $g && Groups::canSee($g, $viewer);
        }
        if ($status !== 'published') {
            return false;
        }
        if (!empty($p['page_id'])) {
            return (bool) Pages::find((int) $p['page_id']);
        }
        return match ($p['privacy']) {
            'public' => !$author['private_account'] || !empty($p['anonymous']) || Users::isFollowing($viewer, $owner),
            'followers' => Users::isFollowing($viewer, $owner),
            default => false,
        };
    }

    public static function canEdit(array $p, int $viewer): bool
    {
        if (!$viewer) {
            return false;
        }
        if (!empty($p['page_id'])) {
            return Pages::isAdmin((int) $p['page_id'], $viewer);
        }
        return (int) $p['user_id'] === $viewer;
    }

    public static function canDelete(array $p, int $viewer): bool
    {
        if (!$viewer) {
            return false;
        }
        if ((int) $p['user_id'] === $viewer || Auth::isMod()) {
            return true;
        }
        if ($p['group_id']) {
            return Groups::isManager((int) $p['group_id'], $viewer);
        }
        if (!empty($p['page_id'])) {
            return Pages::isAdmin((int) $p['page_id'], $viewer);
        }
        return false;
    }

    /** Hide / show a post (moderators). Hidden posts stay for the author and moderators only. */
    public static function setStatus(int $id, string $status, int $by): void
    {
        if (!in_array($status, self::STATUSES, true)) {
            return;
        }
        $p = self::find($id);
        if (!$p) {
            return;
        }
        DB::update('posts', ['status' => $status, 'hidden_by' => $status === 'hidden' ? $by : null, 'hidden_at' => $status === 'hidden' ? time() : null], 'id = ?', [$id]);
        Users::recount((int) $p['user_id']);
        if (!empty($p['page_id'])) {
            Pages::recount((int) $p['page_id']);
        }
        Ext::fire('post_status_changed', $id, $status, $by);
    }

    /* ---------- feeds (cursor: posts with id < $before) ---------- */

    private static function visibleSql(int $viewer, array &$params): string
    {
        $hidden = Users::hiddenIds($viewer);
        $following = $viewer ? Users::followingIds($viewer) : [];
        $params = array_merge($params, $hidden ?: [0], [$viewer], $following ?: [0], $following ?: [0]);
        return " AND p.status = 'published' AND p.user_id NOT IN (" . DB::in($hidden ?: [0]) . ') AND u.banned = 0 AND (p.user_id = ?'
            . ' OR p.page_id IS NOT NULL'
            . " OR (p.privacy = 'public' AND (u.private_account = 0 OR p.anonymous = 1 OR p.user_id IN (" . DB::in($following ?: [0]) . ')))'
            . " OR (p.privacy = 'followers' AND p.user_id IN (" . DB::in($following ?: [0]) . ')))';
    }

    private static function page(string $where, array $params, int $before, int $limit, string $order = 'p.id DESC'): array
    {
        if ($before) {
            $where .= ' AND p.id < ?';
            $params[] = $before;
        }
        return DB::all('SELECT p.* FROM {posts} p JOIN {users} u ON u.id = p.user_id WHERE ' . $where . ' ORDER BY ' . $order . ' LIMIT ' . (int) $limit, $params);
    }

    /** Home: you, people you follow, groups you joined and pages you follow. */
    public static function home(int $viewer, int $before = 0, int $limit = self::PAGE): array
    {
        $following = Users::followingIds($viewer);
        $ids = array_merge([$viewer], $following);
        $groups = Groups::joinedIds($viewer);
        $pages = setting('pages_enabled', '1') === '1' ? Pages::followedIds($viewer) : [];
        $params = array_merge($ids, $groups ?: [0], $pages ?: [0]);
        $where = '((p.group_id IS NULL AND p.page_id IS NULL AND p.user_id IN (' . DB::in($ids) . ')) OR p.group_id IN (' . DB::in($groups ?: [0]) . ')'
            . ' OR p.page_id IN (' . DB::in($pages ?: [0]) . '))';
        $vis = [];
        $where .= self::visibleSql($viewer, $vis);
        return self::page($where, array_merge($params, $vis), $before, $limit);
    }

    /** Explore: public posts from everyone (newest), or the most liked of the last 3 days. */
    public static function explore(int $viewer, int $before = 0, int $limit = self::PAGE, bool $popular = false): array
    {
        $params = [];
        $where = "p.group_id IS NULL AND p.privacy = 'public' AND (u.private_account = 0 OR p.page_id IS NOT NULL OR p.anonymous = 1)";
        $vis = [];
        $where .= self::visibleSql($viewer, $vis);
        if ($popular) {
            $where .= ' AND p.created_at > ?';
            $vis[] = time() - 3 * 86400;
            return self::page($where, array_merge($params, $vis), 0, $limit, 'p.reactions_count + 2 * p.comments_count DESC, p.id DESC');
        }
        return self::page($where, array_merge($params, $vis), $before, $limit);
    }

    public static function profile(array $owner, int $viewer, int $before = 0, int $limit = self::PAGE, bool $mediaOnly = false): array
    {
        $params = [(int) $owner['id']];
        // anonymous posts never show on the author's profile (except to the author)
        $where = 'p.user_id = ? AND p.group_id IS NULL AND p.page_id IS NULL' . ($mediaOnly ? ' AND p.images_count > 0' : '')
            . ((int) $owner['id'] === $viewer ? '' : ' AND p.anonymous = 0');
        $vis = [];
        $where .= self::visibleSql($viewer, $vis);
        return self::page($where, array_merge($params, $vis), $before, $limit);
    }

    public static function inPage(int $pageId, int $viewer, int $before = 0, int $limit = self::PAGE): array
    {
        $hidden = Users::hiddenIds($viewer) ?: [0];
        return self::page("p.page_id = ? AND p.status = 'published' AND u.banned = 0 AND p.user_id NOT IN (" . DB::in($hidden) . ')', array_merge([$pageId], $hidden), $before, $limit);
    }

    public static function hashtag(string $tag, int $viewer, int $before = 0, int $limit = self::PAGE): array
    {
        $hid = (int) DB::value('SELECT id FROM {hashtags} WHERE tag = ?', [mb_strtolower($tag)]);
        if (!$hid) {
            return [];
        }
        $params = [$hid];
        $where = 'p.id IN (SELECT post_id FROM {post_hashtags} WHERE hashtag_id = ?) AND p.group_id IS NULL';
        $vis = [];
        $where .= self::visibleSql($viewer, $vis);
        return self::page($where, array_merge($params, $vis), $before, $limit);
    }

    public static function inGroup(int $groupId, int $viewer, int $before = 0, int $limit = self::PAGE, string $status = 'published'): array
    {
        $hidden = Users::hiddenIds($viewer) ?: [0];
        return self::page('p.group_id = ? AND p.status = ? AND u.banned = 0 AND p.user_id NOT IN (' . DB::in($hidden) . ')', array_merge([$groupId, $status], $hidden), $before, $limit);
    }

    public static function saved(int $viewer, int $before = 0, int $limit = self::PAGE): array
    {
        $rows = DB::all('SELECT p.* FROM {saved} s JOIN {posts} p ON p.id = s.post_id WHERE s.user_id = ?' . ($before ? ' AND p.id < ?' : '') . ' ORDER BY p.id DESC LIMIT ' . (int) $limit,
            $before ? [$viewer, $before] : [$viewer]);
        return array_values(array_filter($rows, fn ($p) => self::canView($p, $viewer)));
    }

    public static function search(string $q, int $viewer, int $limit = 20): array
    {
        $params = [DB::escapeLike($q)];
        $where = DB::like('p.body') . " AND p.group_id IS NULL";
        $vis = [];
        $where .= self::visibleSql($viewer, $vis);
        return self::page($where, array_merge($params, $vis), 0, $limit);
    }

    /** Add author, photos, my like, saved, shared original and group to each post (few queries for a whole page). */
    public static function hydrate(array $posts, int $viewer): array
    {
        if (!$posts) {
            return [];
        }
        $ids = array_map(fn ($p) => (int) $p['id'], $posts);
        $sharedIds = array_filter(array_map(fn ($p) => (int) $p['shared_post_id'], $posts));
        $shared = [];
        if ($sharedIds) {
            foreach (DB::all('SELECT * FROM {posts} WHERE id IN (' . DB::in($sharedIds) . ')', array_values($sharedIds)) as $s) {
                if (self::canView($s, $viewer)) {
                    $shared[(int) $s['id']] = $s;
                }
            }
            $ids = array_merge($ids, array_keys($shared));
        }
        $users = Users::many(array_merge(array_column($posts, 'user_id'), array_column($shared, 'user_id')));
        $images = [];
        foreach (DB::all('SELECT * FROM {post_images} WHERE post_id IN (' . DB::in($ids) . ') ORDER BY position', $ids) as $im) {
            $images[(int) $im['post_id']][] = $im;
        }
        $liked = $savedMap = [];
        if ($viewer) {
            foreach (DB::all('SELECT post_id FROM {reactions} WHERE user_id = ? AND post_id IN (' . DB::in($ids) . ')', array_merge([$viewer], $ids)) as $r) {
                $liked[(int) $r['post_id']] = true;
            }
            foreach (DB::all('SELECT post_id FROM {saved} WHERE user_id = ? AND post_id IN (' . DB::in($ids) . ')', array_merge([$viewer], $ids)) as $r) {
                $savedMap[(int) $r['post_id']] = true;
            }
        }
        $all = array_merge($posts, $shared);
        $groupIds = array_filter(array_map(fn ($p) => (int) $p['group_id'], $all));
        $groups = $groupIds ? Groups::many($groupIds) : [];
        $pageIds = array_filter(array_map(fn ($p) => (int) ($p['page_id'] ?? 0), $all));
        $pages = $pageIds ? Pages::many($pageIds) : [];
        $fill = function (array $p) use ($users, $images, $liked, $savedMap, $groups, $pages) {
            $id = (int) $p['id'];
            $p['author'] = $users[(int) $p['user_id']] ?? null;
            $p['images'] = $images[$id] ?? [];
            $p['liked'] = isset($liked[$id]);
            $p['is_saved'] = isset($savedMap[$id]);
            $p['group'] = $p['group_id'] ? ($groups[(int) $p['group_id']] ?? null) : null;
            $p['page'] = !empty($p['page_id']) ? ($pages[(int) $p['page_id']] ?? null) : null;
            $p['format'] = ($p['format'] ?? 'text') === 'html' ? 'html' : 'text';
            $p['anonymous'] = (int) ($p['anonymous'] ?? 0);
            $p['status'] = $p['status'] ?? 'published';
            return $p;
        };
        $out = [];
        foreach ($posts as $p) {
            $p = $fill($p);
            if (!$p['author']) {
                continue;
            }
            $p['shared'] = ($p['shared_post_id'] && isset($shared[(int) $p['shared_post_id']])) ? $fill($shared[(int) $p['shared_post_id']]) : null;
            $p['shared_missing'] = $p['shared_post_id'] && !$p['shared'];
            $out[] = $p;
        }
        return $out;
    }

    /* ---------- writing ---------- */

    /**
     * Create a post. $files = Upload::files('images'). Returns the new id.
     * $opt: format ('text' or 'html'), anonymous (bool), page_id, status ('published' or 'pending').
     */
    public static function create(int $userId, string $body, string $privacy, array $files = [], ?int $groupId = null, ?int $sharedId = null, array $opt = []): int
    {
        [$body, $format] = self::prepareBody($body, (string) ($opt['format'] ?? 'text'));
        $privacy = in_array($privacy, self::PRIVACY, true) ? $privacy : 'public';
        $pageId = (int) ($opt['page_id'] ?? 0) ?: null;
        $anon = !empty($opt['anonymous']) && !$pageId && !$sharedId ? 1 : 0;
        $status = ($opt['status'] ?? 'published') === 'pending' ? 'pending' : 'published';
        if ($anon || $pageId) {
            $privacy = 'public';
        }
        $max = max(1, min(10, (int) setting('max_post_images', '4')));
        if (count($files) > $max) {
            throw new RuntimeException(t('post.too_many_images', ['n' => $max]));
        }
        if ($body === '' && !$files && !$sharedId) {
            throw new RuntimeException(t('post.empty'));
        }
        $saved = [];
        try {
            foreach ($files as $f) {
                $saved[] = Upload::image($f, 'posts', 1600, 640);
            }
        } catch (RuntimeException $e) {
            foreach ($saved as $s) {
                Upload::remove($s['path']);
                Upload::remove($s['thumb']);
            }
            throw $e;
        }
        $id = DB::transaction(function () use ($userId, $body, $privacy, $saved, $groupId, $sharedId, $format, $anon, $pageId, $status) {
            $id = DB::insert('posts', ['user_id' => $userId, 'group_id' => $groupId, 'body' => $body, 'privacy' => $groupId ? 'public' : $privacy,
                'shared_post_id' => $sharedId, 'images_count' => count($saved), 'created_at' => time(),
                'format' => $format, 'anonymous' => $anon, 'page_id' => $pageId, 'status' => $status]);
            foreach ($saved as $i => $s) {
                DB::insert('post_images', ['post_id' => $id, 'path' => $s['path'], 'thumb' => $s['thumb'], 'width' => $s['width'], 'height' => $s['height'], 'position' => $i]);
            }
            self::syncHashtags($id, Text::plain($body, $format));
            return $id;
        });
        Users::recount($userId);
        if ($pageId) {
            Pages::recount($pageId);
        }
        // anonymous posts do not send mentions: the notification would show who wrote it
        if (!$anon && $status === 'published') {
            foreach (Text::mentions(Text::plain($body, $format)) as $name) {
                $m = Users::byUsername($name);
                if ($m && self::canView(self::find($id), (int) $m['id'])) {
                    Notify::add((int) $m['id'], $userId, 'mention', $id);
                }
            }
        }
        Ext::fire('post_created', $id, $userId);
        return $id;
    }

    /** Clean the text: rich text is sanitised (and saved as plain text when it has no formatting). Returns [body, format]. */
    private static function prepareBody(string $body, string $format): array
    {
        if ($format === 'html' && setting('rich_text', '1') === '1' && Text::richAvailable()) {
            $clean = Text::sanitize(mb_substr($body, 0, self::maxBody('html') * 2));
            if (Text::hasFormatting($clean)) {
                if (mb_strlen($clean) > self::maxBody('html') || mb_strlen(Text::plain($clean, 'html')) > self::maxBody()) {
                    throw new RuntimeException(t('post.too_long', ['n' => self::maxBody()]));
                }
                return [$clean, 'html'];
            }
            $body = Text::plain($clean, 'html');
        } elseif ($format === 'html') {
            $body = Text::plain($body, 'html');
        }
        return [Text::clean($body, self::maxBody()), 'text'];
    }

    public static function edit(int $id, string $body, string $privacy, string $format = 'text'): void
    {
        $p = self::find($id);
        [$body, $format] = self::prepareBody($body, $format);
        if ($body === '' && !$p['images_count'] && !$p['shared_post_id']) {
            throw new RuntimeException(t('post.empty'));
        }
        $fixed = $p['group_id'] || !empty($p['page_id']) || !empty($p['anonymous']);
        DB::update('posts', ['body' => $body, 'format' => $format, 'privacy' => $fixed ? 'public' : (in_array($privacy, self::PRIVACY, true) ? $privacy : $p['privacy']), 'edited_at' => time()], 'id = ?', [$id]);
        self::syncHashtags($id, Text::plain($body, $format));
    }

    private static function syncHashtags(int $postId, string $body): void
    {
        $old = array_map('intval', array_column(DB::all('SELECT hashtag_id FROM {post_hashtags} WHERE post_id = ?', [$postId]), 'hashtag_id'));
        DB::delete('post_hashtags', 'post_id = ?', [$postId]);
        $touched = $old;
        foreach (array_slice(Text::hashtags($body), 0, 20) as $tag) {
            DB::insertIgnore('hashtags', ['tag' => $tag, 'posts_count' => 0, 'last_used' => time()]);
            $hid = (int) DB::value('SELECT id FROM {hashtags} WHERE tag = ?', [$tag]);
            DB::insertIgnore('post_hashtags', ['post_id' => $postId, 'hashtag_id' => $hid, 'created_at' => time()]);
            DB::update('hashtags', ['last_used' => time()], 'id = ?', [$hid]);
            $touched[] = $hid;
        }
        foreach (array_unique($touched) as $hid) {
            DB::update('hashtags', ['posts_count' => (int) DB::value('SELECT COUNT(*) FROM {post_hashtags} WHERE hashtag_id = ?', [$hid])], 'id = ?', [$hid]);
        }
    }

    /** Admin tool: a new photo in place of one photo of a post (same place in the post; the old files are removed). */
    public static function replaceImage(int $imageId, array $file): void
    {
        $im = DB::one('SELECT * FROM {post_images} WHERE id = ?', [$imageId]);
        if (!$im) {
            throw new RuntimeException(t('error.404'));
        }
        $s = Upload::image($file, 'posts', 1600, 640);
        DB::update('post_images', ['path' => $s['path'], 'thumb' => $s['thumb'], 'width' => $s['width'], 'height' => $s['height']], 'id = ?', [$imageId]);
        Upload::remove($im['path']);
        if ($im['thumb'] !== $im['path']) {
            Upload::remove($im['thumb']);
        }
    }

    /** Admin tool: take one photo out of a post. */
    public static function removeImage(int $imageId): void
    {
        $im = DB::one('SELECT * FROM {post_images} WHERE id = ?', [$imageId]);
        if (!$im) {
            return;
        }
        DB::delete('post_images', 'id = ?', [$imageId]);
        DB::update('posts', ['images_count' => (int) DB::value('SELECT COUNT(*) FROM {post_images} WHERE post_id = ?', [$im['post_id']])], 'id = ?', [$im['post_id']]);
        Upload::remove($im['path']);
        if ($im['thumb'] !== $im['path']) {
            Upload::remove($im['thumb']);
        }
    }

    /** Admin tool: add photos to an existing post (up to the site's photo limit). */
    public static function addImages(int $postId, array $files): void
    {
        $have = (int) DB::value('SELECT COUNT(*) FROM {post_images} WHERE post_id = ?', [$postId]);
        $max = max(1, min(10, (int) setting('max_post_images', '4')));
        if ($have + count($files) > $max) {
            throw new RuntimeException(t('post.too_many_images', ['n' => $max]));
        }
        $pos = (int) DB::value('SELECT COALESCE(MAX(position), -1) FROM {post_images} WHERE post_id = ?', [$postId]);
        foreach ($files as $f) {
            $s = Upload::image($f, 'posts', 1600, 640);
            DB::insert('post_images', ['post_id' => $postId, 'path' => $s['path'], 'thumb' => $s['thumb'], 'width' => $s['width'], 'height' => $s['height'], 'position' => ++$pos]);
        }
        DB::update('posts', ['images_count' => (int) DB::value('SELECT COUNT(*) FROM {post_images} WHERE post_id = ?', [$postId])], 'id = ?', [$postId]);
    }

    public static function delete(int $id): void
    {
        $p = self::find($id);
        if (!$p) {
            return;
        }
        $imgs = DB::all('SELECT path, thumb FROM {post_images} WHERE post_id = ?', [$id]);
        DB::transaction(function () use ($id) {
            $tags = array_column(DB::all('SELECT hashtag_id FROM {post_hashtags} WHERE post_id = ?', [$id]), 'hashtag_id');
            foreach (DB::all('SELECT id FROM {comments} WHERE post_id = ?', [$id]) as $c) {
                DB::delete('comment_likes', 'comment_id = ?', [$c['id']]);
            }
            DB::delete('comments', 'post_id = ?', [$id]);
            DB::delete('reactions', 'post_id = ?', [$id]);
            DB::delete('saved', 'post_id = ?', [$id]);
            DB::delete('post_hashtags', 'post_id = ?', [$id]);
            DB::delete('post_images', 'post_id = ?', [$id]);
            DB::delete('notifications', 'post_id = ?', [$id]);
            DB::delete('reports', "target_type = 'post' AND target_id = ?", [$id]);
            DB::query('UPDATE {posts} SET shared_post_id = NULL WHERE shared_post_id = ?', [$id]);
            DB::query('UPDATE {communities} SET pinned_post_id = NULL WHERE pinned_post_id = ?', [$id]);
            DB::delete('posts', 'id = ?', [$id]);
            foreach ($tags as $hid) {
                DB::update('hashtags', ['posts_count' => (int) DB::value('SELECT COUNT(*) FROM {post_hashtags} WHERE hashtag_id = ?', [$hid])], 'id = ?', [$hid]);
            }
        });
        foreach ($imgs as $im) {
            Upload::remove($im['path']);
            if ($im['thumb'] !== $im['path']) {
                Upload::remove($im['thumb']);
            }
        }
        if ($p['shared_post_id']) {
            self::recount((int) $p['shared_post_id']);
        }
        Users::recount((int) $p['user_id']);
        if (!empty($p['page_id'])) {
            Pages::recount((int) $p['page_id']);
        }
        Ext::fire('post_deleted', $id, $p);
    }

    public static function recount(int $id): void
    {
        DB::update('posts', [
            'reactions_count' => (int) DB::value('SELECT COUNT(*) FROM {reactions} WHERE post_id = ?', [$id]),
            'comments_count' => (int) DB::value('SELECT COUNT(*) FROM {comments} WHERE post_id = ?', [$id]),
            'shares_count' => (int) DB::value('SELECT COUNT(*) FROM {posts} WHERE shared_post_id = ?', [$id]),
        ], 'id = ?', [$id]);
    }

    /** Like / unlike. Returns [liked, count]. */
    public static function toggleLike(int $postId, int $viewer): array
    {
        $p = self::find($postId);
        if (!$p || !self::canView($p, $viewer)) {
            throw new RuntimeException(t('error.404'));
        }
        if (DB::delete('reactions', 'post_id = ? AND user_id = ?', [$postId, $viewer])) {
            $liked = false;
        } else {
            DB::insertIgnore('reactions', ['post_id' => $postId, 'user_id' => $viewer, 'type' => 'like', 'created_at' => time()]);
            $liked = true;
            Notify::add((int) $p['user_id'], $viewer, 'reaction', $postId);
        }
        self::recount($postId);
        return [$liked, (int) DB::value('SELECT reactions_count FROM {posts} WHERE id = ?', [$postId])];
    }

    public static function toggleSave(int $postId, int $viewer): bool
    {
        $p = self::find($postId);
        if (!$p || !self::canView($p, $viewer)) {
            throw new RuntimeException(t('error.404'));
        }
        if (DB::delete('saved', 'user_id = ? AND post_id = ?', [$viewer, $postId])) {
            return false;
        }
        DB::insertIgnore('saved', ['user_id' => $viewer, 'post_id' => $postId, 'created_at' => time()]);
        return true;
    }

    /** Share (repost) with an optional comment. Only public posts can be shared. */
    public static function share(int $postId, int $viewer, string $body): int
    {
        $p = self::find($postId);
        if ($p && $p['shared_post_id']) {
            $p = self::find((int) $p['shared_post_id']);
        }
        if (!$p || !self::canView($p, $viewer) || $p['privacy'] !== 'public' || $p['group_id']) {
            throw new RuntimeException(t('post.cannot_share'));
        }
        $id = self::create($viewer, $body, 'public', [], null, (int) $p['id']);
        self::recount((int) $p['id']);
        Notify::add((int) $p['user_id'], $viewer, 'share', (int) $p['id']);
        return $id;
    }

    /* ---------- comments ---------- */

    public static function comments(int $postId, int $viewer, int $limit = 50): array
    {
        $hidden = Users::hiddenIds($viewer) ?: [0];
        $rows = DB::all('SELECT c.* FROM {comments} c JOIN {users} u ON u.id = c.user_id WHERE c.post_id = ? AND u.banned = 0 AND c.user_id NOT IN (' . DB::in($hidden) . ') ORDER BY c.id ASC LIMIT ' . (int) ($limit * 4),
            array_merge([$postId], $hidden));
        $users = Users::many(array_column($rows, 'user_id'));
        $likedSet = [];
        if ($viewer && $rows) {
            $cids = array_column($rows, 'id');
            foreach (DB::all('SELECT comment_id FROM {comment_likes} WHERE user_id = ? AND comment_id IN (' . DB::in($cids) . ')', array_merge([$viewer], $cids)) as $r) {
                $likedSet[(int) $r['comment_id']] = true;
            }
        }
        $top = $replies = [];
        foreach ($rows as $c) {
            $c['author'] = $users[(int) $c['user_id']] ?? null;
            $c['liked'] = isset($likedSet[(int) $c['id']]);
            if (!$c['author']) {
                continue;
            }
            if ($c['parent_id']) {
                $replies[(int) $c['parent_id']][] = $c;
            } else {
                $top[] = $c;
            }
        }
        foreach ($top as &$c) {
            $c['replies'] = $replies[(int) $c['id']] ?? [];
        }
        return array_slice($top, -$limit);
    }

    public static function addComment(int $postId, int $viewer, string $body, ?int $parentId = null): int
    {
        $p = self::find($postId);
        if (!$p || !self::canView($p, $viewer)) {
            throw new RuntimeException(t('error.404'));
        }
        if (setting('comments_enabled', '1') !== '1') {
            throw new RuntimeException(t('comment.closed'));
        }
        $body = Text::clean($body, self::MAX_COMMENT);
        if ($body === '') {
            throw new RuntimeException(t('comment.empty'));
        }
        $parent = null;
        if ($parentId) {
            $parent = DB::one('SELECT * FROM {comments} WHERE id = ? AND post_id = ?', [$parentId, $postId]);
            if (!$parent) {
                throw new RuntimeException(t('error.404'));
            }
            if ($parent['parent_id']) {
                $parentId = (int) $parent['parent_id']; // one level of replies
            }
        }
        $id = DB::insert('comments', ['post_id' => $postId, 'user_id' => $viewer, 'parent_id' => $parentId, 'body' => $body, 'created_at' => time()]);
        self::recount($postId);
        Notify::add((int) $p['user_id'], $viewer, 'comment', $postId, $id);
        if ($parent) {
            Notify::add((int) $parent['user_id'], $viewer, 'reply', $postId, $id);
        }
        foreach (Text::mentions($body) as $name) {
            $m = Users::byUsername($name);
            if ($m && self::canView($p, (int) $m['id'])) {
                Notify::add((int) $m['id'], $viewer, 'mention', $postId, $id);
            }
        }
        Ext::fire('comment_created', $id, $postId, $viewer);
        return $id;
    }

    public static function deleteComment(int $commentId, int $viewer): void
    {
        $c = DB::one('SELECT * FROM {comments} WHERE id = ?', [$commentId]);
        if (!$c) {
            return;
        }
        $p = self::find((int) $c['post_id']);
        if ((int) $c['user_id'] !== $viewer && !($p && (int) $p['user_id'] === $viewer) && !Auth::isMod()) {
            throw new RuntimeException(t('error.403'));
        }
        $ids = array_merge([$commentId], array_map('intval', array_column(DB::all('SELECT id FROM {comments} WHERE parent_id = ?', [$commentId]), 'id')));
        DB::delete('comment_likes', 'comment_id IN (' . DB::in($ids) . ')', $ids);
        DB::delete('notifications', 'comment_id IN (' . DB::in($ids) . ')', $ids);
        DB::delete('comments', 'id IN (' . DB::in($ids) . ')', $ids);
        self::recount((int) $c['post_id']);
    }

    public static function toggleCommentLike(int $commentId, int $viewer): array
    {
        $c = DB::one('SELECT * FROM {comments} WHERE id = ?', [$commentId]);
        $p = $c ? self::find((int) $c['post_id']) : null;
        if (!$c || !$p || !self::canView($p, $viewer)) {
            throw new RuntimeException(t('error.404'));
        }
        if (DB::delete('comment_likes', 'comment_id = ? AND user_id = ?', [$commentId, $viewer])) {
            $liked = false;
        } else {
            DB::insertIgnore('comment_likes', ['comment_id' => $commentId, 'user_id' => $viewer, 'created_at' => time()]);
            $liked = true;
            Notify::add((int) $c['user_id'], $viewer, 'comment_like', (int) $c['post_id'], $commentId);
        }
        $n = (int) DB::value('SELECT COUNT(*) FROM {comment_likes} WHERE comment_id = ?', [$commentId]);
        DB::update('comments', ['likes_count' => $n], 'id = ?', [$commentId]);
        return [$liked, $n];
    }

    public static function trendingTags(int $limit = 8): array
    {
        $unit = ['hours' => 3600, 'days' => 86400, 'weeks' => 604800][setting('trending_unit', 'days')] ?? 86400;
        $since = time() - max(1, (int) setting('trending_window', '7')) * $unit;
        $hidden = self::hiddenTags();
        $rows = DB::all("SELECT h.tag, COUNT(ph.id) AS n FROM {post_hashtags} ph JOIN {hashtags} h ON h.id = ph.hashtag_id JOIN {posts} p ON p.id = ph.post_id WHERE ph.created_at > ? AND p.status = 'published' GROUP BY h.id, h.tag HAVING COUNT(ph.id) >= ? ORDER BY n DESC LIMIT " . (int) ($limit + count($hidden)),
            [$since, max(1, (int) setting('trending_min', '1'))]);
        return array_slice(array_values(array_filter($rows, fn ($r) => !in_array($r['tag'], $hidden, true))), 0, $limit);
    }

    /** Hashtags the admin never wants in trending lists. */
    public static function hiddenTags(): array
    {
        return array_values(array_filter(array_map(fn ($t) => mb_strtolower(trim($t, " #\t\n\r")), explode(',', (string) setting('trending_hidden', '')))));
    }

    /** Featured hashtags set by the admin: [['tag' => ..., 'until' => timestamp or 0 for always], ...]. Expired ones are left out. */
    public static function featuredTags(bool $withExpired = false): array
    {
        $list = json_decode((string) setting('trending_featured', '[]'), true);
        $list = is_array($list) ? $list : [];
        return array_values(array_filter($list, fn ($f) => is_array($f) && isset($f['tag']) && ($withExpired || empty($f['until']) || (int) $f['until'] > time())));
    }

    /**
     * The trending card in the right column: featured tags first, then the most used ones in the admin's time window.
     * Returns [] when the admin switched the card off.
     */
    public static function sidebarTags(): array
    {
        if (setting('trending_enabled', '1') !== '1') {
            return [];
        }
        $limit = max(1, min(20, (int) setting('trending_limit', '6')));
        $out = [];
        foreach (self::featuredTags() as $f) {
            $n = (int) DB::value('SELECT posts_count FROM {hashtags} WHERE tag = ?', [$f['tag']]);
            $out[$f['tag']] = ['tag' => $f['tag'], 'n' => $n, 'featured' => true];
        }
        foreach (self::trendingTags($limit) as $r) {
            $out[$r['tag']] ??= ['tag' => $r['tag'], 'n' => (int) $r['n'], 'featured' => false];
        }
        return array_slice(array_values($out), 0, $limit);
    }
}
