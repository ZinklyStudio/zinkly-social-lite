<?php
/** Zinkly Social Lite - groups (tables: communities, community_members). Public groups: anyone can read and join. Private: members only, joining needs approval. */
final class Groups
{
    public const CATEGORIES = ['general', 'friends', 'family', 'school', 'work', 'local', 'hobbies', 'sports', 'music', 'gaming', 'tech', 'art',
        'travel', 'food', 'health', 'business', 'buy_sell', 'support', 'other'];
    public const ROLES = ['admin', 'moderator', 'member'];
    public static function find(int $id): ?array
    {
        return $id ? DB::one('SELECT * FROM {communities} WHERE id = ?', [$id]) : null;
    }

    public static function many(array $ids): array
    {
        $ids = array_values(array_unique(array_map('intval', $ids)));
        $out = [];
        foreach ($ids ? DB::all('SELECT * FROM {communities} WHERE id IN (' . DB::in($ids) . ')', $ids) : [] as $g) {
            $out[(int) $g['id']] = $g;
        }
        return $out;
    }

    public static function membership(int $groupId, int $user): ?array
    {
        return $user ? DB::one('SELECT * FROM {community_members} WHERE group_id = ? AND user_id = ?', [$groupId, $user]) : null;
    }

    /** 'admin', 'moderator', 'member', 'pending' or null. */
    public static function role(int $groupId, int $user): ?string
    {
        $m = self::membership($groupId, $user);
        if (!$m) {
            return null;
        }
        return $m['status'] === 'pending' ? 'pending' : $m['role'];
    }

    public static function isMember(int $groupId, int $user): bool
    {
        return in_array(self::role($groupId, $user), self::ROLES, true);
    }

    /** Group admins and moderators (and site moderators) manage posts and members. */
    public static function isManager(int $groupId, int $user): bool
    {
        return in_array(self::role($groupId, $user), ['admin', 'moderator'], true) || (Auth::id() === $user && Auth::isMod());
    }

    public static function canSee(array $g, int $viewer): bool
    {
        return $g['privacy'] === 'public' || self::isMember((int) $g['id'], $viewer) || Auth::isMod();
    }

    /** Can this member write in the group? ('members' or 'admins' only, set by the group admins). */
    public static function canPost(array $g, int $user): bool
    {
        if (!self::isMember((int) $g['id'], $user)) {
            return false;
        }
        return ($g['post_permission'] ?? 'members') !== 'admins' || self::isManager((int) $g['id'], $user);
    }

    /** New posts wait for approval when the group asks for it (managers' posts never wait). */
    public static function needsApproval(array $g, int $user): bool
    {
        return !empty($g['post_approval']) && !self::isManager((int) $g['id'], $user);
    }

    /** Save the settings of the Manage dialog. */
    public static function update(array $g, array $in): void
    {
        $data = [
            'name' => Text::clean((string) ($in['name'] ?? $g['name']), 80) ?: $g['name'],
            'description' => Text::clean((string) ($in['description'] ?? ''), 500),
            'rules' => Text::clean((string) ($in['rules'] ?? ''), 3000),
            'category' => in_array($in['category'] ?? '', self::CATEGORIES, true) ? $in['category'] : '',
            'privacy' => ($in['privacy'] ?? '') === 'private' ? 'private' : 'public',
            'visibility' => ($in['visibility'] ?? '') === 'hidden' ? 'hidden' : 'listed',
            'post_permission' => ($in['post_permission'] ?? '') === 'admins' ? 'admins' : 'members',
            'post_approval' => empty($in['post_approval']) ? 0 : 1,
            'allow_anonymous' => empty($in['allow_anonymous']) ? 0 : 1,
        ];
        if (mb_strlen($data['name']) < 3) {
            throw new RuntimeException(t('group.name_short'));
        }
        DB::update('communities', $data, 'id = ?', [$g['id']]);
        // a group that no longer asks for approval publishes what was waiting
        if (!$data['post_approval'] && !empty($g['post_approval'])) {
            DB::query("UPDATE {posts} SET status = 'published' WHERE group_id = ? AND status = 'pending'", [$g['id']]);
        }
    }

    /** Change a member's role. The owner always stays admin. */
    public static function setRole(array $g, int $user, string $role): void
    {
        if (!in_array($role, self::ROLES, true) || (int) $g['owner_id'] === $user) {
            return;
        }
        DB::update('community_members', ['role' => $role], "group_id = ? AND user_id = ? AND status = 'active'", [$g['id'], $user]);
    }

    public static function pin(array $g, ?int $postId): void
    {
        if ($postId && !DB::value("SELECT id FROM {posts} WHERE id = ? AND group_id = ? AND status = 'published'", [$postId, $g['id']])) {
            throw new RuntimeException(t('error.404'));
        }
        DB::update('communities', ['pinned_post_id' => $postId], 'id = ?', [$g['id']]);
    }

    public static function pendingCount(int $groupId): int
    {
        return (int) DB::value("SELECT COUNT(*) FROM {posts} WHERE group_id = ? AND status = 'pending'", [$groupId]);
    }

    public static function joinedIds(int $user): array
    {
        return array_map('intval', array_column(DB::all("SELECT group_id FROM {community_members} WHERE user_id = ? AND status = 'active'", [$user]), 'group_id'));
    }

    public static function create(int $owner, string $name, string $description, string $privacy, string $category = ''): int
    {
        $name = Text::clean($name, 80);
        if (mb_strlen($name) < 3) {
            throw new RuntimeException(t('group.name_short'));
        }
        $slug = slugify($name);
        $base = $slug;
        $i = 2;
        while (DB::value('SELECT id FROM {communities} WHERE slug = ?', [$slug])) {
            $slug = $base . '-' . $i++;
        }
        $id = DB::insert('communities', ['owner_id' => $owner, 'name' => $name, 'slug' => $slug, 'description' => Text::clean($description, 500),
            'privacy' => $privacy === 'private' ? 'private' : 'public', 'members_count' => 1, 'created_at' => time(),
            'category' => in_array($category, self::CATEGORIES, true) ? $category : '']);
        DB::insert('community_members', ['group_id' => $id, 'user_id' => $owner, 'role' => 'admin', 'status' => 'active', 'created_at' => time()]);
        return $id;
    }

    /** Join (public) or ask to join (private). Returns 'member' or 'pending'. */
    public static function join(array $g, int $user): string
    {
        $role = self::role((int) $g['id'], $user);
        if ($role) {
            return $role;
        }
        $status = $g['privacy'] === 'private' ? 'pending' : 'active';
        DB::insertIgnore('community_members', ['group_id' => $g['id'], 'user_id' => $user, 'role' => 'member', 'status' => $status, 'created_at' => time()]);
        self::recount((int) $g['id']);
        if ($status === 'pending') {
            foreach (DB::all("SELECT user_id FROM {community_members} WHERE group_id = ? AND role = 'admin'", [$g['id']]) as $a) {
                Notify::add((int) $a['user_id'], $user, 'group_join', null, null, (int) $g['id']);
            }
        }
        return $status === 'pending' ? 'pending' : 'member';
    }

    public static function leave(array $g, int $user): void
    {
        if ((int) $g['owner_id'] === $user) {
            throw new RuntimeException(t('group.owner_cannot_leave'));
        }
        DB::delete('community_members', 'group_id = ? AND user_id = ?', [$g['id'], $user]);
        self::recount((int) $g['id']);
    }

    public static function approve(int $groupId, int $user): void
    {
        DB::update('community_members', ['status' => 'active'], "group_id = ? AND user_id = ? AND status = 'pending'", [$groupId, $user]);
        self::recount($groupId);
    }

    public static function removeMember(array $g, int $user): void
    {
        if ((int) $g['owner_id'] === $user) {
            return;
        }
        DB::delete('community_members', 'group_id = ? AND user_id = ?', [$g['id'], $user]);
        self::recount((int) $g['id']);
    }

    public static function recount(int $groupId): void
    {
        DB::update('communities', ['members_count' => (int) DB::value("SELECT COUNT(*) FROM {community_members} WHERE group_id = ? AND status = 'active'", [$groupId])], 'id = ?', [$groupId]);
    }

    public static function members(int $groupId, string $status = 'active', int $limit = 60): array
    {
        $rows = DB::all('SELECT * FROM {community_members} WHERE group_id = ? AND status = ? ORDER BY CASE role WHEN \'admin\' THEN 0 WHEN \'moderator\' THEN 1 ELSE 2 END, id LIMIT ' . (int) $limit, [$groupId, $status]);
        $users = Users::many(array_column($rows, 'user_id'));
        $out = [];
        foreach ($rows as $r) {
            if (isset($users[(int) $r['user_id']])) {
                $out[] = ['user' => $users[(int) $r['user_id']], 'role' => $r['role']];
            }
        }
        return $out;
    }

    public static function list(int $viewer, string $q = '', int $limit = 30, string $category = ''): array
    {
        $params = [];
        // public and private groups can be found (private ones ask to join); hidden groups only by their members
        $where = "(visibility <> 'hidden' OR id IN (SELECT group_id FROM {community_members} WHERE user_id = ? AND status = 'active'))";
        $params[] = $viewer;
        if ($q !== '') {
            $where .= ' AND ' . DB::like('name');
            $params[] = DB::escapeLike($q);
        }
        if ($category !== '' && in_array($category, self::CATEGORIES, true)) {
            $where .= ' AND category = ?';
            $params[] = $category;
        }
        return DB::all('SELECT * FROM {communities} WHERE ' . $where . ' ORDER BY members_count DESC, id DESC LIMIT ' . (int) $limit, $params);
    }

    public static function mine(int $viewer): array
    {
        return DB::all("SELECT c.* FROM {communities} c JOIN {community_members} m ON m.group_id = c.id WHERE m.user_id = ? AND m.status = 'active' ORDER BY c.name", [$viewer]);
    }

    public static function delete(int $id): void
    {
        foreach (DB::all('SELECT id FROM {posts} WHERE group_id = ?', [$id]) as $p) {
            Posts::delete((int) $p['id']);
        }
        $g = self::find($id);
        DB::delete('community_members', 'group_id = ?', [$id]);
        DB::delete('notifications', 'group_id = ?', [$id]);
        DB::delete('reports', "target_type = 'group' AND target_id = ?", [$id]);
        DB::delete('communities', 'id = ?', [$id]);
        if ($g) {
            Upload::remove($g['cover']);
            Upload::remove($g['avatar'] ?? null);
        }
    }
}
