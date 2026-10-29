<?php
/** Zinkly Social Lite - members, follows, blocks. */
final class Users
{
    public const RESERVED = ['admin', 'administrator', 'root', 'system', 'support', 'help', 'login', 'logout', 'register', 'install', 'settings',
        'messages', 'notifications', 'explore', 'search', 'hashtag', 'groups', 'stories', 'saved', 'api', 'uploads', 'assets', 'data', 'u', 'p', 'g', 'terms', 'privacy', 'about',
        'pages', 'page', 'info', 'extensions', 'me', 'anonymous', 'moderator'];

    public static function find(int $id): ?array
    {
        return $id ? DB::one('SELECT * FROM {users} WHERE id = ?', [$id]) : null;
    }

    public static function byUsername(string $username): ?array
    {
        return DB::one('SELECT * FROM {users} WHERE LOWER(username) = ?', [mb_strtolower($username)]);
    }

    public static function byLogin(string $login): ?array
    {
        $login = mb_strtolower(trim($login));
        return DB::one('SELECT * FROM {users} WHERE LOWER(username) = ? OR LOWER(email) = ?', [$login, $login]);
    }

    /** Public fields only (for JSON). */
    public static function card(array $u): array
    {
        return ['id' => (int) $u['id'], 'username' => $u['username'], 'name' => display_name($u), 'avatar' => upload_url($u['avatar']),
            'verified' => (bool) $u['verified'], 'initials' => initials(display_name($u))];
    }

    /** Map of id => user for a list of ids. */
    public static function many(array $ids): array
    {
        $ids = array_values(array_unique(array_filter(array_map('intval', $ids))));
        if (!$ids) {
            return [];
        }
        $out = [];
        foreach (DB::all('SELECT * FROM {users} WHERE id IN (' . DB::in($ids) . ')', $ids) as $u) {
            $out[(int) $u['id']] = $u;
        }
        return $out;
    }

    public static function usernameError(string $u, int $exceptId = 0): ?string
    {
        if (!preg_match('/^[A-Za-z0-9_]{3,32}$/', $u)) {
            return t('auth.username_rules');
        }
        if (in_array(strtolower($u), self::RESERVED, true)) {
            return t('auth.username_taken');
        }
        if (DB::value('SELECT id FROM {users} WHERE LOWER(username) = ? AND id <> ?', [strtolower($u), $exceptId])) {
            return t('auth.username_taken');
        }
        return null;
    }

    public static function create(string $username, string $email, string $password, string $name = '', string $role = 'user'): int
    {
        return DB::insert('users', [
            'username' => $username, 'email' => mb_strtolower($email), 'password' => password_hash($password, PASSWORD_DEFAULT),
            'name' => $name, 'role' => $role, 'lang' => Lang::locale(), 'created_at' => time(), 'last_seen' => time(),
        ]);
    }

    /* ---------- relationships ---------- */

    public static function followStatus(int $from, int $to): ?string
    {
        if (!$from || !$to) {
            return null;
        }
        return DB::value('SELECT status FROM {follows} WHERE follower_id = ? AND followee_id = ?', [$from, $to]) ?: null;
    }

    public static function isFollowing(int $from, int $to): bool
    {
        return self::followStatus($from, $to) === 'accepted';
    }

    /** True when either member blocked the other. */
    public static function blockedEither(int $a, int $b): bool
    {
        if (!$a || !$b || $a === $b) {
            return false;
        }
        return (bool) DB::value('SELECT 1 FROM {blocks} WHERE (blocker_id = ? AND blocked_id = ?) OR (blocker_id = ? AND blocked_id = ?)', [$a, $b, $b, $a]);
    }

    public static function hasBlocked(int $a, int $b): bool
    {
        return (bool) DB::value('SELECT 1 FROM {blocks} WHERE blocker_id = ? AND blocked_id = ?', [$a, $b]);
    }

    /** Ids the viewer must not see (blocked either way). */
    public static function hiddenIds(int $viewer): array
    {
        if (!$viewer) {
            return [];
        }
        static $cache = [];
        if (!isset($cache[$viewer])) {
            $a = DB::all('SELECT blocked_id AS id FROM {blocks} WHERE blocker_id = ? UNION SELECT blocker_id AS id FROM {blocks} WHERE blocked_id = ?', [$viewer, $viewer]);
            $cache[$viewer] = array_map(fn ($r) => (int) $r['id'], $a);
        }
        return $cache[$viewer];
    }

    public static function followingIds(int $viewer): array
    {
        return array_map('intval', array_column(DB::all("SELECT followee_id FROM {follows} WHERE follower_id = ? AND status = 'accepted'", [$viewer]), 'followee_id'));
    }

    /** Follow; private accounts get a request. Returns the new status. */
    public static function follow(int $from, int $to): string
    {
        $target = self::find($to);
        if (!$target || $from === $to || $target['banned'] || self::blockedEither($from, $to)) {
            throw new RuntimeException(t('error.403'));
        }
        $existing = self::followStatus($from, $to);
        if ($existing) {
            return $existing;
        }
        $status = $target['private_account'] ? 'pending' : 'accepted';
        DB::insertIgnore('follows', ['follower_id' => $from, 'followee_id' => $to, 'status' => $status, 'created_at' => time()]);
        if ($status === 'accepted') {
            self::recount($from);
            self::recount($to);
            Notify::add($to, $from, 'follow');
        } else {
            Notify::add($to, $from, 'follow_request');
        }
        return $status;
    }

    public static function unfollow(int $from, int $to): void
    {
        DB::delete('follows', 'follower_id = ? AND followee_id = ?', [$from, $to]);
        self::recount($from);
        self::recount($to);
    }

    public static function acceptRequest(int $owner, int $follower): void
    {
        if (DB::update('follows', ['status' => 'accepted'], "follower_id = ? AND followee_id = ? AND status = 'pending'", [$follower, $owner])) {
            self::recount($owner);
            self::recount($follower);
            Notify::add($follower, $owner, 'follow_accepted');
        }
        DB::delete('notifications', "user_id = ? AND actor_id = ? AND type = 'follow_request'", [$owner, $follower]);
    }

    public static function declineRequest(int $owner, int $follower): void
    {
        DB::delete('follows', "follower_id = ? AND followee_id = ? AND status = 'pending'", [$follower, $owner]);
        DB::delete('notifications', "user_id = ? AND actor_id = ? AND type = 'follow_request'", [$owner, $follower]);
    }

    public static function block(int $a, int $b): void
    {
        if ($a === $b) {
            return;
        }
        DB::insertIgnore('blocks', ['blocker_id' => $a, 'blocked_id' => $b, 'created_at' => time()]);
        DB::delete('follows', '(follower_id = ? AND followee_id = ?) OR (follower_id = ? AND followee_id = ?)', [$a, $b, $b, $a]);
        self::recount($a);
        self::recount($b);
    }

    public static function unblock(int $a, int $b): void
    {
        DB::delete('blocks', 'blocker_id = ? AND blocked_id = ?', [$a, $b]);
    }

    public static function recount(int $id): void
    {
        DB::update('users', [
            'followers_count' => (int) DB::value("SELECT COUNT(*) FROM {follows} WHERE followee_id = ? AND status = 'accepted'", [$id]),
            'following_count' => (int) DB::value("SELECT COUNT(*) FROM {follows} WHERE follower_id = ? AND status = 'accepted'", [$id]),
            'posts_count' => (int) DB::value("SELECT COUNT(*) FROM {posts} WHERE user_id = ? AND group_id IS NULL AND page_id IS NULL AND anonymous = 0 AND status = 'published'", [$id]),
        ], 'id = ?', [$id]);
    }

    /** Can the viewer see this member's posts and lists? */
    public static function canSeeContent(array $owner, int $viewer): bool
    {
        if ((int) $owner['id'] === $viewer) {
            return true;
        }
        if ($owner['banned'] || self::blockedEither((int) $owner['id'], $viewer)) {
            return false;
        }
        return !$owner['private_account'] || self::isFollowing($viewer, (int) $owner['id']) || Auth::isMod();
    }

    public static function canMessage(array $to, int $from): bool
    {
        if ((int) $to['id'] === $from || $to['banned'] || self::blockedEither((int) $to['id'], $from)) {
            return false;
        }
        return match ($to['who_can_message']) {
            'nobody' => false,
            'following' => self::isFollowing((int) $to['id'], $from),
            default => true,
        };
    }

    /** People to follow: popular members the viewer does not follow yet. */
    public static function suggestions(int $viewer, int $limit = 5): array
    {
        $skip = array_merge([$viewer], self::followingIds($viewer), self::hiddenIds($viewer));
        return DB::all('SELECT * FROM {users} WHERE banned = 0 AND id NOT IN (' . DB::in($skip) . ') ORDER BY followers_count DESC, id DESC LIMIT ' . (int) $limit, $skip);
    }

    public static function search(string $q, int $viewer, int $limit = 20, int $offset = 0): array
    {
        $like = DB::escapeLike($q);
        $skip = self::hiddenIds($viewer) ?: [0];
        return DB::all('SELECT * FROM {users} WHERE banned = 0 AND (' . DB::like('username') . ' OR ' . DB::like('name') . ') AND id NOT IN (' . DB::in($skip) . ')
            ORDER BY followers_count DESC LIMIT ' . (int) $limit . ' OFFSET ' . (int) $offset, array_merge([$like, $like], $skip));
    }

    public static function online(array $u): bool
    {
        return (int) $u['last_seen'] > time() - 300;
    }

    /** Delete a member and everything they made. */
    public static function deleteAccount(int $id): void
    {
        $u = self::find($id);
        if (!$u) {
            return;
        }
        foreach (DB::all('SELECT id FROM {communities} WHERE owner_id = ?', [$id]) as $g) {
            Groups::delete((int) $g['id']);
        }
        foreach (DB::all('SELECT id FROM {pages} WHERE owner_id = ?', [$id]) as $pg) {
            Pages::delete((int) $pg['id']);
        }
        foreach (DB::all('SELECT id FROM {posts} WHERE user_id = ?', [$id]) as $p) {
            Posts::delete((int) $p['id']);
        }
        foreach (DB::all('SELECT path FROM {stories} WHERE user_id = ? AND path IS NOT NULL', [$id]) as $s) {
            Upload::remove($s['path']);
        }
        foreach (DB::all('SELECT image FROM {messages} WHERE user_id = ? AND image IS NOT NULL', [$id]) as $m) {
            Upload::remove($m['image']);
        }
        DB::transaction(function () use ($id, $u) {
            foreach (DB::all('SELECT id FROM {conversations} WHERE user_a = ? OR user_b = ?', [$id, $id]) as $c) {
                DB::delete('messages', 'conversation_id = ?', [$c['id']]);
            }
            DB::delete('conversations', 'user_a = ? OR user_b = ?', [$id, $id]);
            $affected = array_merge(array_column(DB::all('SELECT followee_id FROM {follows} WHERE follower_id = ?', [$id]), 'followee_id'),
                array_column(DB::all('SELECT follower_id FROM {follows} WHERE followee_id = ?', [$id]), 'follower_id'));
            DB::delete('follows', 'follower_id = ? OR followee_id = ?', [$id, $id]);
            DB::delete('blocks', 'blocker_id = ? OR blocked_id = ?', [$id, $id]);
            foreach (DB::all('SELECT post_id FROM {reactions} WHERE user_id = ?', [$id]) as $r) {
                DB::delete('reactions', 'post_id = ? AND user_id = ?', [$r['post_id'], $id]);
                Posts::recount((int) $r['post_id']);
            }
            foreach (DB::all('SELECT DISTINCT post_id FROM {comments} WHERE user_id = ?', [$id]) as $c) {
                DB::delete('comments', 'post_id = ? AND user_id = ?', [$c['post_id'], $id]);
                Posts::recount((int) $c['post_id']);
            }
            DB::delete('comment_likes', 'user_id = ?', [$id]);
            DB::delete('saved', 'user_id = ?', [$id]);
            DB::delete('login_log', 'user_id = ?', [$id]);
            DB::delete('pro_payments', 'user_id = ?', [$id]);
            DB::delete('notifications', 'user_id = ? OR actor_id = ?', [$id, $id]);
            DB::delete('story_views', 'user_id = ?', [$id]);
            DB::delete('stories', 'user_id = ?', [$id]);
            DB::delete('community_members', 'user_id = ?', [$id]);
            foreach (DB::all('SELECT page_id FROM {page_follows} WHERE user_id = ?', [$id]) as $pf) {
                DB::delete('page_follows', 'page_id = ? AND user_id = ?', [$pf['page_id'], $id]);
                Pages::recount((int) $pf['page_id']);
            }
            DB::delete('page_admins', 'user_id = ?', [$id]);
            DB::delete('reports', 'reporter_id = ?', [$id]);
            DB::delete('users', 'id = ?', [$id]);
            foreach (array_unique(array_map('intval', $affected)) as $a) {
                self::recount($a);
            }
        });
        Upload::remove($u['avatar']);
        Upload::remove($u['cover']);
    }
}
