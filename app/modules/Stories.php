<?php
/** Zinkly Social Lite - 24-hour stories (photo or text). The owner sees how many people viewed, never a list of who liked. */
final class Stories
{
    public const BGS = ['violet', 'sunset', 'ocean', 'forest', 'night', 'rose'];
    public const COLORS = ['white', 'black', 'yellow', 'cyan', 'pink', 'lime'];
    public const FONTS = ['modern', 'serif', 'mono', 'hand'];
    public const POSITIONS = ['top', 'center', 'bottom'];
    /** Video files are kept as they are (no converting on shared hosting), so only formats every browser plays. */
    public const VIDEO_TYPES = ['video/mp4' => 'mp4', 'video/webm' => 'webm', 'video/quicktime' => 'mov'];

    /**
     * $type: image, video or text. $file: an uploaded photo or video. $fromImage: the id of a photo from one of the member's own posts.
     * $style: ['bg' => ..., 'color' => ..., 'font' => ..., 'pos' => ...].
     */
    public static function create(int $user, string $type, ?array $file, string $text, array $style, int $fromImage = 0): int
    {
        $path = null;
        $text = Text::clean($text, 300);
        if ($type === 'image' || $type === 'video') {
            if ($file && self::isVideo($file)) {
                $type = 'video';
                $path = self::video($file);
            } elseif ($file) {
                $type = 'image';
                $path = Upload::image($file, 'stories', 1440, null)['path'];
            } elseif ($fromImage) {
                $type = 'image';
                $path = self::copyPostImage($user, $fromImage);
            } else {
                throw new RuntimeException(t('story.need_photo'));
            }
        } else {
            $type = 'text';
            if ($text === '') {
                throw new RuntimeException(t('story.need_text'));
            }
        }
        $pick = fn (string $k, array $list) => in_array($style[$k] ?? '', $list, true) ? $style[$k] : $list[0];
        return DB::insert('stories', ['user_id' => $user, 'type' => $type, 'path' => $path, 'text_body' => $text !== '' ? $text : null,
            'bg' => $pick('bg', self::BGS), 'text_color' => $pick('color', self::COLORS), 'font' => $pick('font', self::FONTS),
            'text_pos' => in_array($style['pos'] ?? '', self::POSITIONS, true) ? $style['pos'] : 'bottom',
            'expires_at' => time() + 86400, 'created_at' => time()]);
    }

    /** Admin tool: put a new photo or video in place of a story's media (the old file is removed). */
    public static function replaceMedia(int $storyId, array $file): void
    {
        $s = DB::one('SELECT * FROM {stories} WHERE id = ?', [$storyId]);
        if (!$s) {
            throw new RuntimeException(t('error.404'));
        }
        if (self::isVideo($file)) {
            $type = 'video';
            $path = self::video($file);
        } else {
            $type = 'image';
            $path = Upload::image($file, 'stories', 1440, null)['path'];
        }
        DB::update('stories', ['type' => $type, 'path' => $path], 'id = ?', [$storyId]);
        Upload::remove($s['path']);
    }

    public static function videoEnabled(): bool
    {
        return setting('story_video', '1') === '1';
    }

    public static function videoMaxMb(): int
    {
        return max(1, min(200, (int) setting('story_video_mb', '20')));
    }

    private static function isVideo(array $file): bool
    {
        if (($file['error'] ?? 1) !== UPLOAD_ERR_OK || !is_uploaded_file($file['tmp_name'])) {
            return false;
        }
        $mime = (string) (new finfo(FILEINFO_MIME_TYPE))->file($file['tmp_name']);
        return str_starts_with($mime, 'video/');
    }

    /** Save a story video as it is, after checking by content that it really is an mp4 / webm / mov file. */
    private static function video(array $file): string
    {
        if (!self::videoEnabled()) {
            throw new RuntimeException(t('story.video_off'));
        }
        if ($file['size'] > self::videoMaxMb() * 1024 * 1024) {
            throw new RuntimeException(t('upload.too_big', ['mb' => self::videoMaxMb()]));
        }
        $mime = (string) (new finfo(FILEINFO_MIME_TYPE))->file($file['tmp_name']);
        $ext = self::VIDEO_TYPES[$mime] ?? null;
        if (!$ext) {
            throw new RuntimeException(t('story.video_type'));
        }
        $dir = 'stories/' . date('Y/m');
        if (!is_dir(ZL_ROOT . '/uploads/' . $dir) && !mkdir(ZL_ROOT . '/uploads/' . $dir, 0755, true) && !is_dir(ZL_ROOT . '/uploads/' . $dir)) {
            throw new RuntimeException(t('upload.failed'));
        }
        $path = $dir . '/' . bin2hex(random_bytes(12)) . '.' . $ext;
        if (!move_uploaded_file($file['tmp_name'], ZL_ROOT . '/uploads/' . $path)) {
            throw new RuntimeException(t('upload.failed'));
        }
        @chmod(ZL_ROOT . '/uploads/' . $path, 0644);
        return $path;
    }

    /** A copy of a photo from one of the member's own posts (a copy, so deleting the story never touches the post). */
    private static function copyPostImage(int $user, int $imageId): string
    {
        $img = DB::one('SELECT i.path FROM {post_images} i JOIN {posts} p ON p.id = i.post_id WHERE i.id = ? AND p.user_id = ? AND p.page_id IS NULL', [$imageId, $user]);
        $src = $img ? ZL_ROOT . '/uploads/' . $img['path'] : '';
        if (!$img || str_contains($img['path'], '..') || !is_file($src)) {
            throw new RuntimeException(t('story.need_photo'));
        }
        $dir = 'stories/' . date('Y/m');
        if (!is_dir(ZL_ROOT . '/uploads/' . $dir)) {
            @mkdir(ZL_ROOT . '/uploads/' . $dir, 0755, true);
        }
        $path = $dir . '/' . bin2hex(random_bytes(12)) . '.' . pathinfo($img['path'], PATHINFO_EXTENSION);
        if (!@copy($src, ZL_ROOT . '/uploads/' . $path)) {
            throw new RuntimeException(t('upload.failed'));
        }
        return $path;
    }

    /** The member's recent post photos, for "From my posts" in the story composer. */
    public static function myPhotos(int $user, int $limit = 30): array
    {
        return array_map(fn ($r) => ['id' => (int) $r['id'], 'thumb' => upload_url($r['thumb'])],
            DB::all('SELECT i.id, i.thumb FROM {post_images} i JOIN {posts} p ON p.id = i.post_id WHERE p.user_id = ? AND p.page_id IS NULL AND p.status = \'published\' ORDER BY i.id DESC LIMIT ' . (int) $limit, [$user]));
    }

    /** Members with active stories the viewer may see: you first, then people you follow (unseen first). */
    public static function tray(int $viewer): array
    {
        $ids = array_merge([$viewer], Users::followingIds($viewer));
        $hidden = Users::hiddenIds($viewer);
        $ids = array_values(array_diff($ids, $hidden));
        $rows = DB::all('SELECT user_id, MAX(id) AS last_id, COUNT(*) AS n FROM {stories} WHERE expires_at > ? AND user_id IN (' . DB::in($ids) . ') GROUP BY user_id', array_merge([time()], $ids));
        if (!$rows) {
            return [];
        }
        $users = Users::many(array_column($rows, 'user_id'));
        $seen = [];
        foreach (DB::all('SELECT s.user_id, COUNT(*) AS n FROM {story_views} v JOIN {stories} s ON s.id = v.story_id WHERE v.user_id = ? AND s.expires_at > ? GROUP BY s.user_id', [$viewer, time()]) as $r) {
            $seen[(int) $r['user_id']] = (int) $r['n'];
        }
        $out = [];
        foreach ($rows as $r) {
            $u = $users[(int) $r['user_id']] ?? null;
            if (!$u || $u['banned']) {
                continue;
            }
            $out[] = ['user' => $u, 'count' => (int) $r['n'], 'all_seen' => ($seen[(int) $u['id']] ?? 0) >= (int) $r['n'], 'last_id' => (int) $r['last_id']];
        }
        usort($out, function ($a, $b) use ($viewer) {
            if ((int) $a['user']['id'] === $viewer) {
                return -1;
            }
            if ((int) $b['user']['id'] === $viewer) {
                return 1;
            }
            return [$a['all_seen'], -$a['last_id']] <=> [$b['all_seen'], -$b['last_id']];
        });
        return $out;
    }

    public static function ofUser(int $owner, int $viewer): array
    {
        $u = Users::find($owner);
        if (!$u || ($owner !== $viewer && (!Users::canSeeContent($u, $viewer) || !Users::isFollowing($viewer, $owner)) && !Auth::isMod())) {
            return [];
        }
        $rows = DB::all('SELECT * FROM {stories} WHERE user_id = ? AND expires_at > ? ORDER BY id ASC', [$owner, time()]);
        $seen = [];
        if ($rows && $viewer) {
            $ids = array_column($rows, 'id');
            foreach (DB::all('SELECT story_id FROM {story_views} WHERE user_id = ? AND story_id IN (' . DB::in($ids) . ')', array_merge([$viewer], $ids)) as $r) {
                $seen[(int) $r['story_id']] = true;
            }
        }
        return array_map(fn ($s) => [
            'id' => (int) $s['id'], 'type' => $s['type'], 'image' => upload_url($s['path']), 'text' => $s['text_body'] ?? '', 'bg' => $s['bg'] ?: 'violet',
            'color' => $s['text_color'] ?: 'white', 'font' => $s['font'] ?: 'modern', 'pos' => $s['text_pos'] ?: 'bottom',
            'ago' => ago((int) $s['created_at']), 'seen' => isset($seen[(int) $s['id']]), 'views' => $owner === $viewer ? (int) $s['views_count'] : null,
        ], $rows);
    }

    public static function view(int $storyId, int $viewer): void
    {
        $s = DB::one('SELECT * FROM {stories} WHERE id = ? AND expires_at > ?', [$storyId, time()]);
        if (!$s || (int) $s['user_id'] === $viewer) {
            return;
        }
        if (DB::insertIgnore('story_views', ['story_id' => $storyId, 'user_id' => $viewer, 'created_at' => time()])) {
            DB::query('UPDATE {stories} SET views_count = views_count + 1 WHERE id = ?', [$storyId]);
        }
    }

    public static function delete(int $storyId, int $viewer): void
    {
        $s = DB::one('SELECT * FROM {stories} WHERE id = ?', [$storyId]);
        if (!$s || ((int) $s['user_id'] !== $viewer && !Auth::isMod())) {
            throw new RuntimeException(t('error.403'));
        }
        DB::delete('story_views', 'story_id = ?', [$storyId]);
        DB::delete('stories', 'id = ?', [$storyId]);
        Upload::remove($s['path']);
    }
}
