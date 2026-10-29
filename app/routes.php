<?php
/** Zinkly Social Lite - all pages and actions. Every POST is checked for the CSRF token by the router. */

$me = fn (): int => Auth::id();
$fail = function (Throwable $e, string $back = '') {
    if (wants_json()) {
        json_out(['error' => $e instanceof RuntimeException ? $e->getMessage() : t('error.500')], 422);
    }
    flash('error', $e instanceof RuntimeException ? $e->getMessage() : t('error.500'));
    $back !== '' ? redirect($back) : back();
};
$feedJson = function (array $posts, int $viewer) {
    $posts = Posts::hydrate($posts, $viewer);
    $html = '';
    foreach ($posts as $p) {
        $html .= View::fetch('partials/post', ['p' => $p]);
    }
    json_out(['html' => $html, 'next' => $posts ? (int) end($posts)['id'] : 0, 'count' => count($posts)]);
};
$userOr404 = function (string $username): array {
    $u = Users::byUsername($username);
    if (!$u || ($u['banned'] && !Auth::isMod())) {
        abort(404);
    }
    return $u;
};

/* ---------- maintenance mode: only admins get in (they can still log in) ---------- */

if ((setting('maintenance', '0') === '1' || Updater::running()) && !Auth::isAdmin()) {
    $mPath = Router::path();
    if (!in_array($mPath, ['/login', '/logout', '/forgot'], true) && !str_starts_with($mPath, '/reset/')) {
        http_response_code(503);
        header('Retry-After: 3600');
        View::render('pages/maintenance', ['title' => t('maint.title')], 'layout-guest');
        exit;
    }
}

/* ---------- home, landing ---------- */

Router::get('/', function () use ($me) {
    if (!Auth::check()) {
        // Admin > Settings > Landing page: the welcome page, straight to log in, or the public Explore feed
        $mode = (string) setting('landing_mode', 'landing');
        if ($mode === 'login') {
            redirect('login');
        }
        if ($mode === 'explore') {
            redirect('explore');
        }
        $stats = setting('landing_stats', '1') === '1' ? [
            'members' => (int) DB::value('SELECT COUNT(*) FROM {users} WHERE banned = 0'),
            'posts' => (int) DB::value("SELECT COUNT(*) FROM {posts} WHERE status = 'published'"),
            'groups' => (int) DB::value('SELECT COUNT(*) FROM {communities}'),
        ] : null;
        View::render('pages/landing', ['title' => site_name(), 'stats' => $stats], 'layout-guest');
        return;
    }
    $posts = Posts::hydrate(Posts::home($me()), $me());
    View::render('pages/home', [
        'title' => t('nav.home'), 'posts' => $posts, 'tray' => setting('stories_enabled', '1') === '1' ? Stories::tray($me()) : [],
        'suggestions' => Users::suggestions($me(), 5), 'trending' => Posts::sidebarTags(), 'scope' => 'home', 'nav' => 'home',
    ]);
});

Router::get('/feed/more', function () use ($me, $feedJson) {
    $before = (int) input('before', 0);
    $scope = (string) input('scope', 'home');
    $v = $me();
    $posts = match (true) {
        $scope === 'home' && $v > 0 => Posts::home($v, $before),
        $scope === 'explore' => Posts::explore($v, $before),
        str_starts_with($scope, 'user:') => ($u = Users::find((int) substr($scope, 5))) ? Posts::profile($u, $v, $before) : [],
        str_starts_with($scope, 'media:') => ($u = Users::find((int) substr($scope, 6))) ? Posts::profile($u, $v, $before, Posts::PAGE, true) : [],
        str_starts_with($scope, 'tag:') => Posts::hashtag(substr($scope, 4), $v, $before),
        str_starts_with($scope, 'group:') => (($g = Groups::find((int) substr($scope, 6))) && Groups::canSee($g, $v)) ? Posts::inGroup((int) $g['id'], $v, $before) : [],
        str_starts_with($scope, 'page:') => (Pages::enabled() && ($pg = Pages::find((int) substr($scope, 5)))) ? Posts::inPage((int) $pg['id'], $v, $before) : [],
        $scope === 'saved' && $v > 0 => Posts::saved($v, $before),
        default => [],
    };
    $feedJson($posts, $v);
});

/* ---------- auth ---------- */

Router::get('/login', fn () => View::render('pages/login', ['title' => t('auth.login')], 'layout-guest'), ['guest' => true]);

Router::post('/login', function () {
    $login = (string) input('login', '');
    $key = 'login:' . RateLimit::ip() . ':' . mb_strtolower($login);
    if (!RateLimit::hit($key, 6, 900) || !RateLimit::hit('login-ip:' . RateLimit::ip(), 30, 900)) {
        flash('error', t('auth.too_many'));
        redirect('login');
    }
    $u = Users::byLogin($login);
    if (!$u || !password_verify((string) input('password', ''), $u['password'])) {
        if ($u) {
            Security::log((int) $u['id'], 'password', false);
        }
        keep_old($_POST);
        flash('error', t('auth.failed'));
        redirect('login');
    }
    if ($u['banned']) {
        flash('error', t('auth.banned'));
        redirect('login');
    }
    if (password_needs_rehash($u['password'], PASSWORD_DEFAULT)) {
        DB::update('users', ['password' => password_hash((string) input('password'), PASSWORD_DEFAULT)], 'id = ?', [$u['id']]);
    }
    RateLimit::clear($key);
    Auth::login($u, (bool) input('remember'));
    Security::log((int) $u['id'], 'password', true);
    $to = $_SESSION['_intended'] ?? '';
    unset($_SESSION['_intended'], $_SESSION['_old']);
    redirect(is_string($to) && str_starts_with($to, '/') && !str_starts_with($to, '//') ? ltrim($to, '/') : '');
}, ['guest' => true]);

Router::get('/register', function () {
    if (setting('registration_open', '1') !== '1') {
        flash('error', t('auth.registration_closed'));
        redirect('login');
    }
    View::render('pages/register', ['title' => t('auth.register')], 'layout-guest');
}, ['guest' => true]);

Router::post('/register', function () {
    if (setting('registration_open', '1') !== '1') {
        abort(403, t('auth.registration_closed'));
    }
    RateLimit::guard('register:' . RateLimit::ip(), 5, 3600);
    keep_old($_POST);
    $username = (string) input('username', '');
    $email = (string) input('email', '');
    $password = (string) input('password', '');
    $err = Users::usernameError($username)
        ?? (filter_var($email, FILTER_VALIDATE_EMAIL) ? null : t('auth.email_invalid'))
        ?? (DB::value('SELECT id FROM {users} WHERE LOWER(email) = ?', [mb_strtolower($email)]) ? t('auth.email_taken') : null)
        ?? Auth::passwordError($password)
        ?? (input('agree') ? null : t('auth.must_agree'));
    if ($err) {
        flash('error', $err);
        redirect('register');
    }
    $id = Users::create($username, $email, $password, Text::clean((string) input('name', ''), 80));
    unset($_SESSION['_old']);
    Auth::login(Users::find($id));
    Security::log($id, 'register', true);
    flash('success', t('auth.welcome', ['name' => $username]));
    redirect('');
}, ['guest' => true]);

Router::post('/logout', function () {
    Auth::logout();
    redirect('login');
});

Router::get('/forgot', fn () => View::render('pages/forgot', ['title' => t('auth.forgot')], 'layout-guest'), ['guest' => true]);

Router::post('/forgot', function () {
    RateLimit::guard('forgot:' . RateLimit::ip(), 5, 3600);
    $u = Users::byLogin((string) input('email', ''));
    if ($u && !$u['banned']) {
        $token = bin2hex(random_bytes(24));
        DB::update('users', ['reset_token' => hash('sha256', $token), 'reset_expires' => time() + 3600], 'id = ?', [$u['id']]);
        Mailer::send($u['email'], t('auth.reset_subject', ['site' => site_name()]),
            t('auth.reset_mail', ['name' => display_name($u), 'link' => full_url('reset/' . $token), 'site' => site_name()]));
    }
    flash('success', t('auth.reset_sent')); // same answer whether or not the address exists
    redirect('login');
}, ['guest' => true]);

Router::get('/reset/{token}', function ($token) {
    $u = DB::one('SELECT id FROM {users} WHERE reset_token = ? AND reset_expires > ?', [hash('sha256', $token), time()]);
    if (!$u) {
        flash('error', t('auth.reset_invalid'));
        redirect('forgot');
    }
    View::render('pages/reset', ['title' => t('auth.new_password'), 'token' => $token], 'layout-guest');
}, ['guest' => true]);

Router::post('/reset/{token}', function ($token) {
    RateLimit::guard('reset:' . RateLimit::ip(), 10, 3600);
    $u = DB::one('SELECT * FROM {users} WHERE reset_token = ? AND reset_expires > ?', [hash('sha256', $token), time()]);
    if (!$u) {
        flash('error', t('auth.reset_invalid'));
        redirect('forgot');
    }
    $p = (string) input('password', '');
    if ($err = Auth::passwordError($p)) {
        flash('error', $err);
        redirect('reset/' . $token);
    }
    DB::update('users', ['password' => password_hash($p, PASSWORD_DEFAULT), 'reset_token' => null, 'reset_expires' => null, 'remember_token' => null], 'id = ?', [$u['id']]);
    Security::signOutEverywhere((int) $u['id']);
    flash('success', t('auth.password_changed'));
    redirect('login');
}, ['guest' => true]);

/* ---------- language & theme (also for guests) ---------- */

Router::post('/prefs', function () {
    $lang = (string) input('lang', '');
    $theme = (string) input('theme', '');
    if (isset(Lang::LANGS[$lang])) {
        setcookie('zl_lang', $lang, ['expires' => time() + 31536000, 'path' => base_url() === '' ? '/' : base_url() . '/', 'samesite' => 'Lax', 'httponly' => true]);
        if (Auth::check()) {
            DB::update('users', ['lang' => $lang], 'id = ?', [Auth::id()]);
        }
    }
    if (in_array($theme, ['dark', 'light', 'system'], true) && Auth::check()) {
        DB::update('users', ['theme' => $theme], 'id = ?', [Auth::id()]);
    }
    if (wants_json()) {
        json_out(['ok' => true]);
    }
    back();
});

/* ---------- explore, hashtags, search ---------- */

Router::get('/explore', function () use ($me) {
    $popular = input('tab') === 'popular';
    View::render('pages/explore', ['title' => t('nav.explore'), 'nav' => 'explore', 'popular' => $popular,
        'posts' => Posts::hydrate(Posts::explore($me(), 0, Posts::PAGE, $popular), $me()), 'scope' => $popular ? '' : 'explore',
        'trending' => Posts::trendingTags(10), 'suggestions' => Auth::check() ? Users::suggestions($me(), 6) : []]);
});

Router::get('/hashtag/{tag}', function ($tag) use ($me) {
    $tag = mb_strtolower(mb_substr($tag, 0, 64));
    View::render('pages/hashtag', ['title' => '#' . $tag, 'tag' => $tag, 'nav' => 'explore',
        'posts' => Posts::hydrate(Posts::hashtag($tag, $me()), $me()), 'scope' => 'tag:' . $tag,
        'count' => (int) DB::value('SELECT posts_count FROM {hashtags} WHERE tag = ?', [$tag])]);
});

Router::get('/search', function () use ($me) {
    $q = Text::clean((string) input('q', ''), 80);
    $type = in_array(input('type'), ['people', 'posts', 'tags', 'groups', 'pages'], true) ? input('type') : 'people';
    $results = [];
    if (mb_strlen($q) >= 2) {
        RateLimit::guard('search:' . ($me() ?: RateLimit::ip()), 60, 60);
        $results = match ($type) {
            'posts' => Posts::hydrate(Posts::search($q, $me()), $me()),
            'tags' => DB::all('SELECT * FROM {hashtags} WHERE ' . DB::like('tag') . ' ORDER BY posts_count DESC LIMIT 30', [DB::escapeLike(ltrim($q, '#'))]),
            'groups' => Groups::list($me(), $q),
            'pages' => Pages::enabled() ? Pages::list($q) : [],
            default => Users::search($q, $me()),
        };
    }
    View::render('pages/search', ['title' => t('nav.search'), 'nav' => 'search', 'q' => $q, 'type' => $type, 'results' => $results]);
});

Router::get('/api/search', function () use ($me) {
    $q = Text::clean((string) input('q', ''), 60);
    if (mb_strlen($q) < 2) {
        json_out(['users' => [], 'tags' => []]);
    }
    RateLimit::guard('search:' . ($me() ?: RateLimit::ip()), 60, 60);
    json_out([
        'users' => array_map(fn ($u) => Users::card($u) + ['url' => url('u/' . $u['username'])], Users::search($q, $me(), 6)),
        'tags' => array_map(fn ($h) => ['tag' => $h['tag'], 'url' => url('hashtag/' . rawurlencode($h['tag'])), 'n' => (int) $h['posts_count']],
            DB::all('SELECT * FROM {hashtags} WHERE ' . DB::like('tag') . ' ORDER BY posts_count DESC LIMIT 4', [DB::escapeLike(ltrim($q, '#'))])),
    ]);
});

/* ---------- profiles & follows ---------- */

Router::get('/u/{username}', function ($username) use ($me, $userOr404) {
    $u = $userOr404($username);
    $v = $me();
    $canSee = Users::canSeeContent($u, $v);
    $media = input('tab') === 'media';
    View::render('pages/profile', [
        'title' => display_name($u) . ' (@' . $u['username'] . ')', 'u' => $u, 'canSee' => $canSee, 'tab' => $media ? 'media' : 'posts',
        'posts' => $canSee ? Posts::hydrate(Posts::profile($u, $v, 0, Posts::PAGE, $media), $v) : [],
        'scope' => ($media ? 'media:' : 'user:') . $u['id'], 'follow' => Users::followStatus($v, (int) $u['id']),
        'blocked' => $v ? Users::hasBlocked($v, (int) $u['id']) : false, 'blockedMe' => $v ? Users::hasBlocked((int) $u['id'], $v) : false,
        'requests' => ($v === (int) $u['id'] && $u['private_account']) ? DB::all("SELECT u.* FROM {follows} f JOIN {users} u ON u.id = f.follower_id WHERE f.followee_id = ? AND f.status = 'pending' ORDER BY f.id DESC LIMIT 50", [$v]) : [],
        'nav' => $v === (int) $u['id'] ? 'profile' : '', 'desc' => str_limit($u['bio'], 160),
    ]);
});

Router::get('/u/{username}/{list}', function ($username, $list) use ($me, $userOr404) {
    if (!in_array($list, ['followers', 'following'], true)) {
        abort(404);
    }
    $u = $userOr404($username);
    if (!Users::canSeeContent($u, $me())) {
        abort(403, t('profile.private'));
    }
    $sql = $list === 'followers'
        ? "SELECT u.* FROM {follows} f JOIN {users} u ON u.id = f.follower_id WHERE f.followee_id = ? AND f.status = 'accepted' AND u.banned = 0 ORDER BY f.id DESC LIMIT 200"
        : "SELECT u.* FROM {follows} f JOIN {users} u ON u.id = f.followee_id WHERE f.follower_id = ? AND f.status = 'accepted' AND u.banned = 0 ORDER BY f.id DESC LIMIT 200";
    View::render('pages/follow-list', ['title' => t('profile.' . $list), 'u' => $u, 'list' => $list, 'people' => DB::all($sql, [$u['id']])]);
});

Router::post('/u/{username}/{action}', function ($username, $action) use ($me, $userOr404, $fail) {
    $u = $userOr404($username);
    $v = $me();
    RateLimit::guard('social:' . $v, 60, 60);
    try {
        $status = match ($action) {
            'follow' => Users::follow($v, (int) $u['id']),
            'unfollow' => (Users::unfollow($v, (int) $u['id']) ?? 'none'),
            'block' => (Users::block($v, (int) $u['id']) ?? 'blocked'),
            'unblock' => (Users::unblock($v, (int) $u['id']) ?? 'none'),
            'accept' => (Users::acceptRequest($v, (int) $u['id']) ?? 'accepted'),
            'decline' => (Users::declineRequest($v, (int) $u['id']) ?? 'declined'),
            default => abort(404),
        };
    } catch (Throwable $e) {
        $fail($e);
    }
    if (wants_json()) {
        json_out(['status' => $status, 'followers' => (int) DB::value('SELECT followers_count FROM {users} WHERE id = ?', [$u['id']])]);
    }
    back();
}, ['auth' => true]);

/* ---------- posts ---------- */

Router::post('/posts', function () use ($me, $fail) {
    RateLimit::guard('post:' . $me(), 10, 60);
    $groupId = (int) input('group_id', 0) ?: null;
    $pageId = (int) input('page_id', 0) ?: null;
    $status = 'published';
    $anon = (bool) input('anonymous') && setting('anonymous_posts', '1') === '1';
    try {
        if ($groupId) {
            $g = Groups::find($groupId);
            if (!$g || !Groups::isMember($groupId, $me())) {
                throw new RuntimeException(t('group.members_only'));
            }
            if (!Groups::canPost($g, $me())) {
                throw new RuntimeException(t('group.admins_only'));
            }
            $status = Groups::needsApproval($g, $me()) ? 'pending' : 'published';
            $anon = $anon && !empty($g['allow_anonymous']);
            $pageId = null;
        } elseif ($pageId) {
            if (!Pages::enabled() || !Pages::find($pageId) || !Pages::isAdmin($pageId, $me())) {
                throw new RuntimeException(t('error.403'));
            }
            $anon = false;
        }
        $id = Posts::create($me(), (string) input('body', ''), (string) input('privacy', 'public'), Upload::files('images'), $groupId, null,
            ['format' => (string) input('format', 'text'), 'anonymous' => $anon, 'page_id' => $pageId, 'status' => $status]);
        if ($groupId) {
            $type = $status === 'pending' ? 'group_post_pending' : 'group_post';
            $managers = DB::all("SELECT user_id FROM {community_members} WHERE group_id = ? AND role IN ('admin','moderator') AND status = 'active' AND user_id <> ?", [$groupId, $me()]);
            // group admins and moderators may see who wrote an anonymous post in their group (like the site staff)
            foreach ($managers as $a) {
                Notify::add((int) $a['user_id'], $me(), $type, $id, null, $groupId);
            }
        }
    } catch (Throwable $e) {
        $fail($e);
    }
    if ($status === 'pending') {
        if (wants_json()) {
            json_out(['html' => '', 'id' => $id, 'message' => t('group.post_pending')]);
        }
        flash('success', t('group.post_pending'));
        back();
    }
    if (wants_json()) {
        $p = Posts::hydrate([Posts::find($id)], $me());
        json_out(['html' => View::fetch('partials/post', ['p' => $p[0], 'fresh' => true]), 'id' => $id, 'message' => t('post.published')]);
    }
    flash('success', t('post.published'));
    back();
}, ['auth' => true]);

Router::get('/p/{id}', function ($id) use ($me) {
    $p = Posts::find((int) $id);
    if (!$p || !Posts::canView($p, $me())) {
        abort(404);
    }
    $h = Posts::hydrate([$p], $me());
    if (!$h) {
        abort(404);
    }
    $plain = Text::plain((string) $p['body'], $h[0]['format']);
    $who = $h[0]['page'] ? $h[0]['page']['name'] : ($h[0]['anonymous'] ? t('anon.name') : display_name($h[0]['author']));
    View::render('pages/post', ['title' => $who . ': ' . str_limit($plain, 60), 'p' => $h[0],
        'comments' => Posts::comments((int) $id, $me()), 'desc' => str_limit($plain, 160),
        'ogImage' => $h[0]['images'] ? full_url('uploads/' . $h[0]['images'][0]['thumb']) : null]);
});

Router::post('/p/{id}/{action}', function ($id, $action) use ($me, $fail) {
    $id = (int) $id;
    $v = $me();
    $p = Posts::find($id);
    if (!$p) {
        abort(404);
    }
    try {
        switch ($action) {
            case 'like':
                RateLimit::guard('like:' . $v, 120, 60);
                [$liked, $n] = Posts::toggleLike($id, $v);
                json_out(['liked' => $liked, 'count' => $n]);
            case 'save':
                $s = Posts::toggleSave($id, $v);
                json_out(['saved' => $s, 'message' => $s ? t('post.saved') : t('post.unsaved')]);
            case 'share':
                RateLimit::guard('post:' . $v, 10, 60);
                $nid = Posts::share($id, $v, (string) input('body', ''));
                if (wants_json()) {
                    json_out(['id' => $nid, 'message' => t('post.shared')]);
                }
                flash('success', t('post.shared'));
                redirect('p/' . $nid);
            case 'comment':
                RateLimit::guard('comment:' . $v, 20, 60);
                $cid = Posts::addComment($id, $v, (string) input('body', ''), (int) input('parent_id', 0) ?: null);
                if (wants_json()) {
                    $c = DB::one('SELECT * FROM {comments} WHERE id = ?', [$cid]);
                    $c['author'] = Auth::user();
                    $c['liked'] = false;
                    $c['replies'] = [];
                    json_out(['html' => View::fetch('partials/comment', ['c' => $c, 'p' => $p, 'reply' => (bool) $c['parent_id']]), 'parent' => (int) $c['parent_id'],
                        'count' => (int) DB::value('SELECT comments_count FROM {posts} WHERE id = ?', [$id])]);
                }
                redirect('p/' . $id . '#c' . $cid);
            case 'edit':
                if (!Posts::canEdit($p, $v)) {
                    abort(403);
                }
                Posts::edit($id, (string) input('body', ''), (string) input('privacy', $p['privacy']), (string) input('format', 'text'));
                if (wants_json()) {
                    $np = Posts::find($id);
                    json_out(['html' => Text::body($np['body'], $np['format']), 'format' => $np['format'], 'message' => t('post.updated')]);
                }
                flash('success', t('post.updated'));
                redirect('p/' . $id);
            case 'delete':
                if (!Posts::canDelete($p, $v)) {
                    abort(403);
                }
                Posts::delete($id);
                if (wants_json()) {
                    json_out(['deleted' => true, 'message' => t('post.deleted')]);
                }
                flash('success', t('post.deleted'));
                redirect('');
            case 'hide':
            case 'unhide':
                if (!Auth::isMod()) {
                    abort(403);
                }
                Posts::setStatus($id, $action === 'hide' ? 'hidden' : 'published', $v);
                $msg = $action === 'hide' ? t('post.hidden_done') : t('post.unhidden_done');
                if (wants_json()) {
                    json_out(['message' => $msg, 'status' => $action === 'hide' ? 'hidden' : 'published']);
                }
                flash('success', $msg);
                back();
            case 'approve':
            case 'reject':
                if (!$p['group_id'] || $p['status'] !== 'pending' || !Groups::isManager((int) $p['group_id'], $v)) {
                    abort(403);
                }
                if ($action === 'approve') {
                    Posts::setStatus($id, 'published', $v);
                    Notify::add((int) $p['user_id'], $v, 'group_post_approved', $id, null, (int) $p['group_id']);
                } else {
                    Posts::delete($id);
                }
                flash('success', $action === 'approve' ? t('group.post_approved') : t('group.post_rejected'));
                back();
            case 'pin':
            case 'unpin':
                $g = $p['group_id'] ? Groups::find((int) $p['group_id']) : null;
                if (!$g || !Groups::isManager((int) $g['id'], $v)) {
                    abort(403);
                }
                Groups::pin($g, $action === 'pin' ? $id : null);
                $msg = $action === 'pin' ? t('group.pinned') : t('group.unpinned');
                if (wants_json()) {
                    json_out(['message' => $msg, 'reload' => true]);
                }
                flash('success', $msg);
                back();
        }
    } catch (Throwable $e) {
        $fail($e);
    }
    abort(404);
}, ['auth' => true]);

Router::get('/p/{id}/comments', function ($id) use ($me) {
    $p = Posts::find((int) $id);
    if (!$p || !Posts::canView($p, $me())) {
        abort(404);
    }
    $html = '';
    foreach (Posts::comments((int) $id, $me()) as $c) {
        $html .= View::fetch('partials/comment', ['c' => $c, 'p' => $p, 'reply' => false]);
    }
    json_out(['html' => $html]);
});

Router::post('/c/{id}/{action}', function ($id, $action) use ($me, $fail) {
    try {
        if ($action === 'like') {
            RateLimit::guard('like:' . $me(), 120, 60);
            [$liked, $n] = Posts::toggleCommentLike((int) $id, $me());
            json_out(['liked' => $liked, 'count' => $n]);
        }
        if ($action === 'delete') {
            Posts::deleteComment((int) $id, $me());
            if (wants_json()) {
                json_out(['deleted' => true]);
            }
            back();
        }
    } catch (Throwable $e) {
        $fail($e);
    }
    abort(404);
}, ['auth' => true]);

Router::get('/saved', function () use ($me) {
    View::render('pages/saved', ['title' => t('nav.saved'), 'nav' => 'saved', 'posts' => Posts::hydrate(Posts::saved($me()), $me()), 'scope' => 'saved']);
}, ['auth' => true]);

Router::post('/report', function () use ($me, $fail) {
    RateLimit::guard('report:' . $me(), 10, 600);
    try {
        Reports::add($me(), (string) input('type', ''), (int) input('id', 0), (string) input('reason', ''), (string) input('details', ''));
    } catch (Throwable $e) {
        $fail($e);
    }
    if (wants_json()) {
        json_out(['message' => t('report.thanks')]);
    }
    flash('success', t('report.thanks'));
    back();
}, ['auth' => true]);

/* ---------- notifications & badges ---------- */

Router::get('/notifications', function () use ($me) {
    $list = Notify::list($me(), 40, (int) input('before', 0));
    Notify::markAllRead($me());
    View::render('pages/notifications', ['title' => t('nav.notifications'), 'nav' => 'notifications', 'items' => $list,
        'requests' => (int) DB::value("SELECT COUNT(*) FROM {follows} WHERE followee_id = ? AND status = 'pending'", [$me()])]);
}, ['auth' => true]);

Router::get('/api/badges', function () use ($me) {
    json_out(['notifications' => Notify::unread($me()), 'messages' => setting('messages_enabled', '1') === '1' ? Messages::unreadTotal($me()) : 0]);
}, ['auth' => true]);

/* ---------- messages ---------- */

$msgsOn = function () {
    if (setting('messages_enabled', '1') !== '1') {
        abort(404);
    }
};

Router::get('/messages', function () use ($me, $msgsOn) {
    $msgsOn();
    // people to start a chat with: those you follow first, then suggestions; only people who accept your messages
    $talked = [];
    $inbox = Messages::inbox($me());
    foreach ($inbox as $c) {
        $talked[(int) $c['other']['id']] = true;
    }
    $starters = [];
    foreach (array_merge(array_values(Users::many(array_slice(Users::followingIds($me()), 0, 30))), Users::suggestions($me(), 10)) as $s) {
        if (count($starters) >= 6 || isset($starters[(int) $s['id']]) || isset($talked[(int) $s['id']]) || $s['banned'] || !Users::canMessage($s, $me())) {
            continue;
        }
        $starters[(int) $s['id']] = $s;
    }
    View::render('pages/messages', ['noAside' => true, 'title' => t('nav.messages'), 'nav' => 'messages', 'inbox' => $inbox, 'conv' => null, 'starters' => array_values($starters)]);
}, ['auth' => true]);

Router::get('/messages/new/{username}', function ($username) use ($me, $msgsOn, $userOr404) {
    $msgsOn();
    $u = $userOr404($username);
    if (!Users::canMessage($u, $me())) {
        flash('error', t('msg.cannot_message'));
        redirect('u/' . $u['username']);
    }
    $c = Messages::conversation($me(), (int) $u['id']);
    redirect('messages/' . $c['id']);
}, ['auth' => true]);

Router::get('/messages/{id}', function ($id) use ($me, $msgsOn) {
    $msgsOn();
    $c = Messages::find((int) $id, $me());
    if (!$c) {
        abort(404);
    }
    $other = Users::find(Messages::otherId($c, $me()));
    Messages::markRead($c, $me());
    View::render('pages/messages', ['noAside' => true, 'title' => $other ? display_name($other) : t('nav.messages'), 'nav' => 'messages', 'inbox' => Messages::inbox($me()),
        'conv' => $c, 'other' => $other, 'msgs' => Messages::messages((int) $c['id']), 'canSend' => $other && Users::canMessage($other, $me())]);
}, ['auth' => true]);

Router::get('/messages/{id}/poll', function ($id) use ($me) {
    $c = Messages::find((int) $id, $me());
    if (!$c) {
        abort(404);
    }
    $rows = Messages::messages((int) $c['id'], (int) input('after', 0));
    if ($rows) {
        Messages::markRead($c, $me());
    }
    $lastMine = DB::one('SELECT read_at FROM {messages} WHERE conversation_id = ? AND user_id = ? ORDER BY id DESC LIMIT 1', [$c['id'], $me()]);
    json_out(['messages' => array_map(fn ($m) => Messages::json($m, $me()), $rows), 'seen' => $lastMine && $lastMine['read_at'] !== null]);
}, ['auth' => true]);

Router::post('/messages/{id}/send', function ($id) use ($me, $fail) {
    RateLimit::guard('msg:' . $me(), 30, 60);
    $c = Messages::find((int) $id, $me());
    if (!$c) {
        abort(404);
    }
    try {
        $files = Upload::files('image');
        $m = Messages::send($c, $me(), (string) input('body', ''), $files[0] ?? null);
    } catch (Throwable $e) {
        $fail($e);
    }
    if (wants_json()) {
        json_out(['message' => Messages::json($m, $me())]);
    }
    redirect('messages/' . (int) $id);
}, ['auth' => true]);

/* ---------- stories ---------- */

Router::get('/stories/my-photos', function () use ($me) {
    json_out(['photos' => Stories::myPhotos($me())]);
}, ['auth' => true]);

Router::get('/stories/{userId}', function ($userId) use ($me) {
    $u = Users::find((int) $userId);
    if (!$u) {
        abort(404);
    }
    json_out(['user' => Users::card($u) + ['url' => url('u/' . $u['username'])], 'mine' => (int) $u['id'] === $me(), 'stories' => Stories::ofUser((int) $userId, $me())]);
}, ['auth' => true]);

Router::post('/stories', function () use ($me, $fail) {
    if (setting('stories_enabled', '1') !== '1') {
        abort(404);
    }
    RateLimit::guard('story:' . $me(), 10, 600);
    try {
        $files = Upload::files('media');
        Stories::create($me(), (string) input('type', 'image'), $files[0] ?? null, (string) input('text', ''),
            ['bg' => (string) input('bg', ''), 'color' => (string) input('color', ''), 'font' => (string) input('font', ''), 'pos' => (string) input('pos', '')],
            (int) input('from_image', 0));
    } catch (Throwable $e) {
        $fail($e);
    }
    flash('success', t('story.added'));
    redirect('');
}, ['auth' => true]);

Router::post('/stories/{id}/view', function ($id) use ($me) {
    Stories::view((int) $id, $me());
    json_out(['ok' => true]);
}, ['auth' => true]);

Router::post('/stories/{id}/delete', function ($id) use ($me, $fail) {
    try {
        Stories::delete((int) $id, $me());
    } catch (Throwable $e) {
        $fail($e);
    }
    json_out(['deleted' => true]);
}, ['auth' => true]);

/* ---------- groups ---------- */

$groupsOn = function () {
    if (setting('groups_enabled', '1') !== '1') {
        abort(404);
    }
};

Router::get('/groups', function () use ($me, $groupsOn) {
    $groupsOn();
    $q = Text::clean((string) input('q', ''), 60);
    $cat = in_array(input('cat'), Groups::CATEGORIES, true) ? (string) input('cat') : '';
    View::render('pages/groups', ['title' => t('nav.groups'), 'nav' => 'groups', 'q' => $q, 'cat' => $cat, 'mine' => Groups::mine($me()), 'all' => Groups::list($me(), $q, 30, $cat)]);
}, ['auth' => true]);

Router::post('/groups', function () use ($me, $fail, $groupsOn) {
    $groupsOn();
    RateLimit::guard('group-create:' . $me(), 5, 3600);
    try {
        $id = Groups::create($me(), (string) input('name', ''), (string) input('description', ''), (string) input('privacy', 'public'), (string) input('category', ''));
    } catch (Throwable $e) {
        $fail($e, 'groups');
    }
    redirect('g/' . $id);
}, ['auth' => true]);

Router::get('/g/{id}', function ($id) use ($me, $groupsOn) {
    $groupsOn();
    $g = Groups::find((int) $id);
    if (!$g) {
        abort(404);
    }
    $can = Groups::canSee($g, $me());
    $role = Groups::role((int) $g['id'], $me());
    $manager = Groups::isManager((int) $g['id'], $me());
    $tab = in_array(input('tab'), ['posts', 'members', 'about', 'review'], true) ? (string) input('tab') : 'posts';
    if ($tab === 'review' && !$manager) {
        $tab = 'posts';
    }
    $pinned = null;
    if ($can && $tab === 'posts' && $g['pinned_post_id']) {
        $pp = Posts::find((int) $g['pinned_post_id']);
        $pinned = $pp && $pp['status'] === 'published' ? (Posts::hydrate([$pp], $me())[0] ?? null) : null;
    }
    $posts = [];
    if ($can && $tab === 'posts') {
        $posts = array_values(array_filter(Posts::hydrate(Posts::inGroup((int) $g['id'], $me()), $me()), fn ($p) => !$pinned || (int) $p['id'] !== (int) $pinned['id']));
    } elseif ($tab === 'review') {
        $posts = Posts::hydrate(Posts::inGroup((int) $g['id'], $me(), 0, 50, 'pending'), $me());
    }
    View::render('pages/group', ['title' => $g['name'], 'nav' => 'groups', 'g' => $g, 'can' => $can, 'role' => $role, 'manager' => $manager, 'tab' => $tab,
        'posts' => $posts, 'pinned' => $pinned, 'scope' => $tab === 'posts' ? 'group:' . $g['id'] : '',
        'members' => Groups::members((int) $g['id'], 'active', $tab === 'members' ? 500 : 12),
        'pending' => $manager ? Groups::members((int) $g['id'], 'pending', 50) : [], 'pendingPosts' => $manager ? Groups::pendingCount((int) $g['id']) : 0,
        'desc' => str_limit($g['description'], 160)]);
}, ['auth' => true]);

Router::post('/g/{id}/{action}', function ($id, $action) use ($me, $fail, $groupsOn) {
    $groupsOn();
    $g = Groups::find((int) $id);
    if (!$g) {
        abort(404);
    }
    $isAdmin = Groups::role((int) $g['id'], $me()) === 'admin' || Auth::isMod();
    $isManager = Groups::isManager((int) $g['id'], $me());
    $back = 'g/' . (int) $g['id'];
    try {
        switch ($action) {
            case 'join':
                $r = Groups::join($g, $me());
                flash('success', $r === 'pending' ? t('group.requested') : t('group.joined'));
                break;
            case 'leave':
                Groups::leave($g, $me());
                flash('success', t('group.left'));
                break;
            case 'approve':
            case 'remove':
                if (!$isManager) {
                    abort(403);
                }
                $uid = (int) input('user_id', 0);
                // moderators may not remove admins
                if ($action === 'remove' && !$isAdmin && Groups::role((int) $g['id'], $uid) === 'admin') {
                    abort(403);
                }
                $action === 'approve' ? Groups::approve((int) $g['id'], $uid) : Groups::removeMember($g, $uid);
                $back .= input('tab') ? '?tab=' . rawurlencode((string) input('tab')) : '';
                break;
            case 'make-admin':
            case 'set-role':
                if (!$isAdmin) {
                    abort(403);
                }
                Groups::setRole($g, (int) input('user_id', 0), $action === 'make-admin' ? 'admin' : (string) input('role', 'member'));
                flash('success', t('settings.saved'));
                $back .= '?tab=members';
                break;
            case 'edit':
                if (!$isAdmin) {
                    abort(403);
                }
                Groups::update($g, $_POST);
                foreach (['cover' => [1500, false], 'avatar' => [400, true]] as $field => [$size, $sq]) {
                    $files = Upload::files($field);
                    if ($files) {
                        $img = Upload::image($files[0], 'groups', $size, null, $sq);
                        Upload::remove($g[$field]);
                        DB::update('communities', [$field => $img['path']], 'id = ?', [$g['id']]);
                    }
                }
                flash('success', t('settings.saved'));
                break;
            case 'photo':
                if (!$isAdmin) {
                    abort(403);
                }
                $field = input('type') === 'avatar' ? 'avatar' : 'cover';
                $url = null;
                if (input('remove')) {
                    Upload::remove($g[$field]);
                    DB::update('communities', [$field => null], 'id = ?', [$g['id']]);
                } else {
                    $files = Upload::files('photo');
                    if (!$files) {
                        throw new RuntimeException(t('upload.failed'));
                    }
                    $img = Upload::image($files[0], 'groups', $field === 'cover' ? 1500 : 400, null, $field === 'avatar');
                    Upload::remove($g[$field]);
                    DB::update('communities', [$field => $img['path']], 'id = ?', [$g['id']]);
                    $url = upload_url($img['path']);
                }
                if (wants_json()) {
                    json_out(['url' => $url, 'message' => t('settings.saved')]);
                }
                break;
            case 'delete':
                if ((int) $g['owner_id'] !== $me() && !Auth::isAdmin()) {
                    abort(403);
                }
                Groups::delete((int) $g['id']);
                flash('success', t('group.deleted'));
                redirect(input('from') === 'admin' ? 'admin/groups' : 'groups');
            default:
                abort(404);
        }
    } catch (Throwable $e) {
        $fail($e);
    }
    redirect($back);
}, ['auth' => true]);

/* ---------- pages (brands, artists, places ...) ---------- */

$pagesOn = function () {
    if (!Pages::enabled()) {
        abort(404);
    }
};
$pageOr404 = function (string $slug): array {
    $pg = Pages::bySlug($slug);
    if (!$pg) {
        abort(404);
    }
    return $pg;
};

Router::get('/pages', function () use ($me, $pagesOn) {
    $pagesOn();
    $q = Text::clean((string) input('q', ''), 60);
    $cat = in_array(input('cat'), Pages::CATEGORIES, true) ? (string) input('cat') : '';
    View::render('pages/pages', ['title' => t('nav.pages'), 'nav' => 'pages', 'q' => $q, 'cat' => $cat,
        'mine' => $me() ? Pages::mine($me()) : [], 'all' => Pages::list($q, $cat, 40), 'following' => Pages::followedIds($me())]);
});

Router::post('/pages', function () use ($me, $fail, $pagesOn) {
    $pagesOn();
    RateLimit::guard('page-create:' . $me(), 5, 3600);
    try {
        $id = Pages::create($me(), (string) input('name', ''), (string) input('category', ''), (string) input('description', ''));
    } catch (Throwable $e) {
        $fail($e, 'pages');
    }
    redirect('pages/' . Pages::find($id)['slug']);
}, ['auth' => true]);

Router::get('/pages/{slug}', function ($slug) use ($me, $pagesOn, $pageOr404) {
    $pagesOn();
    $pg = $pageOr404($slug);
    $tab = in_array(input('tab'), ['posts', 'about'], true) ? (string) input('tab') : 'posts';
    $isAdmin = Pages::isAdmin((int) $pg['id'], $me());
    View::render('pages/page', ['title' => $pg['name'], 'nav' => 'pages', 'pg' => $pg, 'tab' => $tab, 'isAdmin' => $isAdmin,
        'following' => Pages::isFollowing((int) $pg['id'], $me()), 'admins' => Pages::admins((int) $pg['id']),
        'posts' => $tab === 'posts' ? Posts::hydrate(Posts::inPage((int) $pg['id'], $me()), $me()) : [], 'scope' => 'page:' . $pg['id'],
        'desc' => str_limit($pg['description'], 160), 'ogImage' => $pg['avatar'] ? full_url('uploads/' . $pg['avatar']) : null]);
});

Router::post('/pages/{slug}/{action}', function ($slug, $action) use ($me, $fail, $pagesOn, $pageOr404) {
    $pagesOn();
    $pg = $pageOr404($slug);
    $isAdmin = Pages::isAdmin((int) $pg['id'], $me());
    $isOwner = (int) $pg['owner_id'] === $me() || Auth::isAdmin();
    try {
        switch ($action) {
            case 'follow':
                RateLimit::guard('social:' . $me(), 60, 60);
                $on = Pages::toggleFollow($pg, $me());
                if (wants_json()) {
                    json_out(['following' => $on, 'followers' => (int) DB::value('SELECT followers_count FROM {pages} WHERE id = ?', [$pg['id']])]);
                }
                break;
            case 'edit':
                if (!$isAdmin) {
                    abort(403);
                }
                Pages::update($pg, $_POST);
                flash('success', t('settings.saved'));
                break;
            case 'photo':
                if (!$isAdmin) {
                    abort(403);
                }
                $field = input('type') === 'avatar' ? 'avatar' : 'cover';
                $url = null;
                if (input('remove')) {
                    Upload::remove($pg[$field]);
                    DB::update('pages', [$field => null], 'id = ?', [$pg['id']]);
                } else {
                    $files = Upload::files('photo');
                    if (!$files) {
                        throw new RuntimeException(t('upload.failed'));
                    }
                    $img = Upload::image($files[0], 'pages', $field === 'cover' ? 1500 : 400, null, $field === 'avatar');
                    Upload::remove($pg[$field]);
                    DB::update('pages', [$field => $img['path']], 'id = ?', [$pg['id']]);
                    $url = upload_url($img['path']);
                }
                if (wants_json()) {
                    json_out(['url' => $url, 'message' => t('settings.saved')]);
                }
                break;
            case 'add-admin':
            case 'remove-admin':
                if (!$isOwner) {
                    abort(403);
                }
                $action === 'add-admin' ? Pages::addAdmin($pg, (string) input('username', '')) : Pages::removeAdmin($pg, (int) input('user_id', 0));
                flash('success', t('settings.saved'));
                break;
            case 'delete':
                if (!$isOwner) {
                    abort(403);
                }
                Pages::delete((int) $pg['id']);
                flash('success', t('pages.deleted'));
                redirect(input('from') === 'admin' ? 'admin/pages' : 'pages');
            default:
                abort(404);
        }
    } catch (Throwable $e) {
        $fail($e);
    }
    redirect('pages/' . $pg['slug'] . ($action === 'edit' || str_contains($action, 'admin') ? '?tab=about' : ''));
}, ['auth' => true]);

/* ---------- site pages written by admins ---------- */

Router::get('/info/{slug}', function ($slug) {
    $sp = SitePages::bySlug($slug);
    if (!$sp && Auth::isAdmin()) {
        $sp = DB::one('SELECT * FROM {site_pages} WHERE slug = ?', [mb_strtolower($slug)]);
    }
    if (!$sp) {
        abort(404);
    }
    View::render('pages/text', ['title' => $sp['title'], 'html' => Text::renderHtml($sp['body']), 'sitePage' => $sp,
        'desc' => str_limit(Text::plain($sp['body'], 'html'), 160)], Auth::check() ? 'layout' : 'layout-guest');
});

/* ---------- change your photo or cover from the profile page ---------- */

Router::post('/me/photo', function () use ($me, $fail) {
    RateLimit::guard('photo:' . $me(), 20, 600);
    $u = Auth::user();
    $field = input('type') === 'cover' ? 'cover' : 'avatar';
    $url = null;
    try {
        if (input('remove')) {
            Upload::remove($u[$field]);
            DB::update('users', [$field => null], 'id = ?', [$u['id']]);
        } else {
            $files = Upload::files('photo');
            if (!$files) {
                throw new RuntimeException(t('upload.failed'));
            }
            $img = Upload::image($files[0], $field . 's', $field === 'cover' ? 1500 : 400, null, $field === 'avatar');
            Upload::remove($u[$field]);
            DB::update('users', [$field => $img['path']], 'id = ?', [$u['id']]);
            $url = upload_url($img['path']);
        }
    } catch (Throwable $e) {
        $fail($e);
    }
    if (wants_json()) {
        json_out(['url' => $url, 'message' => $field === 'cover' ? t('photo.cover_saved') : t('photo.avatar_saved')]);
    }
    back();
}, ['auth' => true]);

/* ---------- settings ---------- */

Router::get('/settings', function () use ($me) {
    $tab = in_array(input('tab'), ['profile', 'account', 'privacy', 'blocked', 'display'], true) ? input('tab') : 'profile';
    View::render('pages/settings', ['noAside' => true, 'title' => t('nav.settings'), 'nav' => 'settings', 'tab' => $tab, 'u' => Auth::user(),
        'blockedUsers' => $tab === 'blocked' ? DB::all('SELECT u.* FROM {blocks} b JOIN {users} u ON u.id = b.blocked_id WHERE b.blocker_id = ? ORDER BY b.id DESC', [$me()]) : []]);
}, ['auth' => true]);

Router::post('/settings/{section}', function ($section) use ($me, $fail) {
    $u = Auth::user();
    try {
        switch ($section) {
            case 'profile':
                $data = ['name' => Text::clean((string) input('name', ''), 80), 'bio' => Text::clean((string) input('bio', ''), 300),
                    'location' => Text::clean((string) input('location', ''), 80)];
                $web = trim((string) input('website', ''));
                if ($web !== '' && !preg_match('~^https?://~i', $web)) {
                    $web = 'https://' . $web;
                }
                $data['website'] = ($web === '' || filter_var($web, FILTER_VALIDATE_URL)) ? mb_substr($web, 0, 190) : $u['website'];
                foreach (['avatar' => [400, true], 'cover' => [1500, false]] as $field => [$size, $sq]) {
                    $files = Upload::files($field);
                    if ($files) {
                        $img = Upload::image($files[0], $field . 's', $size, null, $sq);
                        Upload::remove($u[$field]);
                        $data[$field] = $img['path'];
                    } elseif (input('remove_' . $field)) {
                        Upload::remove($u[$field]);
                        $data[$field] = null;
                    }
                }
                $newName = (string) input('username', $u['username']);
                if ($newName !== $u['username']) {
                    if ($err = Users::usernameError($newName, (int) $u['id'])) {
                        throw new RuntimeException($err);
                    }
                    $data['username'] = $newName;
                }
                DB::update('users', $data, 'id = ?', [$u['id']]);
                break;
            case 'account':
                if (!password_verify((string) input('current_password', ''), $u['password'])) {
                    throw new RuntimeException(t('settings.wrong_password'));
                }
                $email = (string) input('email', $u['email']);
                if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                    throw new RuntimeException(t('auth.email_invalid'));
                }
                if (DB::value('SELECT id FROM {users} WHERE LOWER(email) = ? AND id <> ?', [mb_strtolower($email), $u['id']])) {
                    throw new RuntimeException(t('auth.email_taken'));
                }
                $data = ['email' => mb_strtolower($email)];
                $new = (string) input('new_password', '');
                if ($new !== '') {
                    if ($err = Auth::passwordError($new)) {
                        throw new RuntimeException($err);
                    }
                    $data['password'] = password_hash($new, PASSWORD_DEFAULT);
                    $data['remember_token'] = null;
                }
                DB::update('users', $data, 'id = ?', [$u['id']]);
                break;
            case 'privacy':
                $priv = input('private_account') ? 1 : 0;
                DB::update('users', ['private_account' => $priv, 'who_can_message' => in_array(input('who_can_message'), ['everyone', 'following', 'nobody'], true) ? input('who_can_message') : 'everyone'], 'id = ?', [$u['id']]);
                if (!$priv && $u['private_account']) { // going public: pending requests are accepted
                    foreach (DB::all("SELECT follower_id FROM {follows} WHERE followee_id = ? AND status = 'pending'", [$u['id']]) as $r) {
                        Users::acceptRequest((int) $u['id'], (int) $r['follower_id']);
                    }
                }
                break;
            case 'display':
                $lang = (string) input('lang', '');
                $theme = (string) input('theme', 'system');
                DB::update('users', ['lang' => isset(Lang::LANGS[$lang]) ? $lang : '', 'theme' => in_array($theme, ['dark', 'light', 'system'], true) ? $theme : 'system'], 'id = ?', [$u['id']]);
                break;
            case 'delete':
                if (!password_verify((string) input('current_password', ''), $u['password'])) {
                    throw new RuntimeException(t('settings.wrong_password'));
                }
                if ($u['role'] === 'admin' && (int) DB::value("SELECT COUNT(*) FROM {users} WHERE role = 'admin'") <= 1) {
                    throw new RuntimeException(t('settings.last_admin'));
                }
                Auth::logout();
                Users::deleteAccount((int) $u['id']);
                flash('success', t('settings.account_deleted'));
                redirect('login');
            default:
                abort(404);
        }
    } catch (Throwable $e) {
        $fail($e, 'settings?tab=' . $section);
    }
    flash('success', t('settings.saved'));
    redirect('settings?tab=' . $section);
}, ['auth' => true]);

/* ---------- static pages, sitemap ---------- */

Router::get('/terms', fn () => View::render('pages/text', ['title' => t('page.terms'), 'body' => (string) setting('terms_text', '') ?: t('page.terms_default', ['site' => site_name()])],
    Auth::check() ? 'layout' : 'layout-guest'));
Router::get('/privacy', fn () => View::render('pages/text', ['title' => t('page.privacy'), 'body' => (string) setting('privacy_text', '') ?: t('page.privacy_default', ['site' => site_name()])],
    Auth::check() ? 'layout' : 'layout-guest'));

Router::get('/sitemap.xml', function () {
    header('Content-Type: application/xml; charset=utf-8');
    echo '<?xml version="1.0" encoding="UTF-8"?>' . "\n" . '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">';
    echo '<url><loc>' . e(full_url('')) . '</loc></url>';
    if (setting('noindex', '0') !== '1') {
        foreach (DB::all('SELECT username, last_seen FROM {users} WHERE banned = 0 AND private_account = 0 ORDER BY followers_count DESC LIMIT 5000') as $u) {
            echo '<url><loc>' . e(full_url('u/' . $u['username'])) . '</loc></url>';
        }
    }
    echo '</urlset>';
});

/* ---------- admin ---------- */

require ZL_ROOT . '/app/routes-pro.php';
require ZL_ROOT . '/app/routes-members.php';
require ZL_ROOT . '/app/routes-admin.php';
