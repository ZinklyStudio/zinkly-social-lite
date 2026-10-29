<?php
/** Zinkly Social Lite - Admin panel (admins only; moderators can use Reports and Posts). */

$adminOnly = ['auth' => true, 'admin' => true];
$modOnly = ['auth' => true];
$requireMod = function () {
    if (!Auth::isMod()) {
        abort(403);
    }
};

Router::get('/admin', function () {
    $day = time() - 86400;
    $stats = [
        'users' => (int) DB::value('SELECT COUNT(*) FROM {users}'),
        'users_new' => (int) DB::value('SELECT COUNT(*) FROM {users} WHERE created_at > ?', [$day]),
        'online' => (int) DB::value('SELECT COUNT(*) FROM {users} WHERE last_seen > ?', [time() - 300]),
        'posts' => (int) DB::value('SELECT COUNT(*) FROM {posts}'),
        'posts_new' => (int) DB::value('SELECT COUNT(*) FROM {posts} WHERE created_at > ?', [$day]),
        'comments' => (int) DB::value('SELECT COUNT(*) FROM {comments}'),
        'groups' => (int) DB::value('SELECT COUNT(*) FROM {communities}'),
        'reports' => (int) DB::value("SELECT COUNT(*) FROM {reports} WHERE status = 'open'"),
        'messages' => (int) DB::value('SELECT COUNT(*) FROM {messages}'),
        'stories' => (int) DB::value('SELECT COUNT(*) FROM {stories} WHERE expires_at > ?', [time()]),
        'pages' => (int) DB::value('SELECT COUNT(*) FROM {pages}'),
        'hidden' => (int) DB::value("SELECT COUNT(*) FROM {posts} WHERE status = 'hidden'"),
        'anonymous' => (int) DB::value('SELECT COUNT(*) FROM {posts} WHERE anonymous = 1'),
        'extensions' => (int) DB::value('SELECT COUNT(*) FROM {extensions} WHERE enabled = 1'),
    ];
    $chart = [];
    for ($i = 13; $i >= 0; $i--) {
        $from = strtotime('today') - $i * 86400;
        $chart[] = ['label' => date('j/n', $from), 'users' => (int) DB::value('SELECT COUNT(*) FROM {users} WHERE created_at >= ? AND created_at < ?', [$from, $from + 86400]),
            'posts' => (int) DB::value('SELECT COUNT(*) FROM {posts} WHERE created_at >= ? AND created_at < ?', [$from, $from + 86400])];
    }
    $uploads = 0;
    $it = is_dir(ZL_ROOT . '/uploads') ? new RecursiveIteratorIterator(new RecursiveDirectoryIterator(ZL_ROOT . '/uploads', FilesystemIterator::SKIP_DOTS)) : [];
    foreach ($it as $f) {
        $uploads += $f->getSize();
    }
    View::render('admin/dashboard', ['noAside' => true, 'title' => t('admin.dashboard'), 'nav' => 'admin', 'anav' => 'dashboard', 'stats' => $stats, 'chart' => $chart,
        'latest' => DB::all('SELECT * FROM {users} ORDER BY id DESC LIMIT 6'), 'uploadsBytes' => $uploads,
        'env' => ['php' => PHP_VERSION, 'db' => DB::driver(), 'version' => setting('version', Installer::VERSION)]]);
}, $adminOnly);

Router::get('/admin/users', function () {
    $q = Text::clean((string) input('q', ''), 60);
    $page = max(1, (int) input('page', 1));
    $where = '1=1';
    $params = [];
    if ($q !== '') {
        $where = '(' . DB::like('username') . ' OR ' . DB::like('email') . ' OR ' . DB::like('name') . ')';
        $l = DB::escapeLike($q);
        $params = [$l, $l, $l];
    }
    $filter = (string) input('filter', '');
    if ($filter === 'banned') {
        $where .= ' AND banned = 1';
    } elseif ($filter === 'staff') {
        $where .= " AND role IN ('admin','moderator')";
    }
    $total = (int) DB::value("SELECT COUNT(*) FROM {users} WHERE $where", $params);
    $users = DB::all("SELECT * FROM {users} WHERE $where ORDER BY id DESC LIMIT 30 OFFSET " . (($page - 1) * 30), $params);
    View::render('admin/users', ['noAside' => true, 'title' => t('admin.users'), 'nav' => 'admin', 'anav' => 'users', 'users' => $users, 'q' => $q, 'filter' => $filter,
        'page' => $page, 'pages' => max(1, (int) ceil($total / 30)), 'total' => $total]);
}, $adminOnly);

Router::post('/admin/users/{id}/{action}', function ($id, $action) {
    $u = Users::find((int) $id);
    if (!$u) {
        abort(404);
    }
    if ((int) $u['id'] === Auth::id() && in_array($action, ['ban', 'role', 'delete'], true)) {
        flash('error', t('admin.not_yourself'));
        back();
    }
    switch ($action) {
        case 'ban':
            DB::update('users', ['banned' => 1, 'remember_token' => null], 'id = ?', [$u['id']]);
            break;
        case 'unban':
            DB::update('users', ['banned' => 0], 'id = ?', [$u['id']]);
            break;
        case 'verify':
            DB::update('users', ['verified' => $u['verified'] ? 0 : 1], 'id = ?', [$u['id']]);
            break;
        case 'role':
            $role = in_array(input('role'), ['user', 'moderator', 'admin'], true) ? input('role') : 'user';
            DB::update('users', ['role' => $role], 'id = ?', [$u['id']]);
            break;
        case 'delete':
            Users::deleteAccount((int) $u['id']);
            break;
        default:
            abort(404);
    }
    flash('success', t('admin.done'));
    back();
}, $adminOnly);

/* ---------- posts: every post on the site, with filters, hide / show, delete, and the real author of anonymous posts ---------- */

Router::get('/admin/posts', function () use ($requireMod) {
    $requireMod();
    $f = [
        'q' => Text::clean((string) input('q', ''), 80),
        'status' => in_array(input('status'), Posts::STATUSES, true) ? (string) input('status') : '',
        'type' => in_array(input('type'), ['anonymous', 'group', 'page', 'profile', 'rich', 'photos', 'reported', 'shared'], true) ? (string) input('type') : '',
        'author' => Text::clean((string) input('author', ''), 40),
    ];
    $page = max(1, (int) input('page', 1));
    $per = 25;
    $where = ['1=1'];
    $params = [];
    if ($f['q'] !== '') {
        $where[] = DB::like('p.body');
        $params[] = DB::escapeLike($f['q']);
    }
    if ($f['status'] !== '') {
        $where[] = 'p.status = ?';
        $params[] = $f['status'];
    }
    if ($f['author'] !== '') {
        $where[] = 'LOWER(u.username) = ?';
        $params[] = mb_strtolower(ltrim($f['author'], '@'));
    }
    $where[] = match ($f['type']) {
        'anonymous' => 'p.anonymous = 1',
        'group' => 'p.group_id IS NOT NULL',
        'page' => 'p.page_id IS NOT NULL',
        'profile' => 'p.group_id IS NULL AND p.page_id IS NULL AND p.anonymous = 0',
        'rich' => "p.format = 'html'",
        'photos' => 'p.images_count > 0',
        'shared' => 'p.shared_post_id IS NOT NULL',
        'reported' => "p.id IN (SELECT target_id FROM {reports} WHERE target_type = 'post' AND status = 'open')",
        default => '1=1',
    };
    $w = implode(' AND ', $where);
    $total = (int) DB::value("SELECT COUNT(*) FROM {posts} p JOIN {users} u ON u.id = p.user_id WHERE $w", $params);
    $rows = DB::all("SELECT p.* FROM {posts} p JOIN {users} u ON u.id = p.user_id WHERE $w ORDER BY p.id DESC LIMIT $per OFFSET " . (($page - 1) * $per), $params);
    $posts = Posts::hydrate($rows, Auth::id());
    $reported = [];
    if ($posts) {
        $ids = array_map(fn ($p) => (int) $p['id'], $posts);
        foreach (DB::all("SELECT target_id, COUNT(*) AS n FROM {reports} WHERE target_type = 'post' AND status = 'open' AND target_id IN (" . DB::in($ids) . ') GROUP BY target_id', $ids) as $r) {
            $reported[(int) $r['target_id']] = (int) $r['n'];
        }
    }
    $counts = [
        'all' => (int) DB::value('SELECT COUNT(*) FROM {posts}'),
        'published' => (int) DB::value("SELECT COUNT(*) FROM {posts} WHERE status = 'published'"),
        'hidden' => (int) DB::value("SELECT COUNT(*) FROM {posts} WHERE status = 'hidden'"),
        'pending' => (int) DB::value("SELECT COUNT(*) FROM {posts} WHERE status = 'pending'"),
        'anonymous' => (int) DB::value('SELECT COUNT(*) FROM {posts} WHERE anonymous = 1'),
    ];
    View::render('admin/posts', ['noAside' => true, 'title' => t('admin.posts'), 'nav' => 'admin', 'anav' => 'posts', 'posts' => $posts, 'f' => $f,
        'page' => $page, 'pages' => max(1, (int) ceil($total / $per)), 'total' => $total, 'reported' => $reported, 'counts' => $counts]);
}, $modOnly);

Router::post('/admin/posts/bulk', function () use ($requireMod) {
    $requireMod();
    $ids = array_slice(array_values(array_filter(array_map('intval', (array) ($_POST['ids'] ?? [])))), 0, 200);
    $action = (string) input('action', '');
    if (!$ids || !in_array($action, ['hide', 'unhide', 'delete'], true)) {
        flash('error', t('admin.bulk_none'));
        back();
    }
    foreach ($ids as $id) {
        match ($action) {
            'hide' => Posts::setStatus($id, 'hidden', Auth::id()),
            'unhide' => Posts::setStatus($id, 'published', Auth::id()),
            'delete' => Posts::delete($id),
        };
    }
    flash('success', t('admin.bulk_done', ['n' => count($ids)]));
    back();
}, $modOnly);

Router::get('/admin/reports', function () use ($requireMod) {
    $requireMod();
    $rows = Reports::open(60);
    $reporters = Users::many(array_column($rows, 'reporter_id'));
    foreach ($rows as &$r) {
        $r['reporter'] = $reporters[(int) $r['reporter_id']] ?? null;
        $r['preview'] = match ($r['target_type']) {
            'post' => ($p = Posts::find((int) $r['target_id'])) ? ['text' => str_limit((string) $p['body'], 200), 'url' => url('p/' . $p['id'])] : null,
            'comment' => ($c = DB::one('SELECT * FROM {comments} WHERE id = ?', [$r['target_id']])) ? ['text' => str_limit($c['body'], 200), 'url' => url('p/' . $c['post_id'] . '#c' . $c['id'])] : null,
            'user' => ($u = Users::find((int) $r['target_id'])) ? ['text' => '@' . $u['username'], 'url' => url('u/' . $u['username'])] : null,
            'group' => ($g = Groups::find((int) $r['target_id'])) ? ['text' => $g['name'], 'url' => url('g/' . $g['id'])] : null,
            'page' => ($pg = Pages::find((int) $r['target_id'])) ? ['text' => $pg['name'], 'url' => url('pages/' . $pg['slug'])] : null,
            default => null,
        };
    }
    View::render('admin/reports', ['noAside' => true, 'title' => t('admin.reports'), 'nav' => 'admin', 'anav' => 'reports', 'reports' => $rows]);
}, $modOnly);

Router::post('/admin/reports/{id}/{action}', function ($id, $action) use ($requireMod) {
    $requireMod();
    $r = DB::one('SELECT * FROM {reports} WHERE id = ?', [(int) $id]);
    if (!$r) {
        abort(404);
    }
    if ($action === 'hide' && $r['target_type'] === 'post') {
        Posts::setStatus((int) $r['target_id'], 'hidden', Auth::id());
    }
    if ($action === 'remove') {
        match ($r['target_type']) {
            'post' => Posts::delete((int) $r['target_id']),
            'comment' => Posts::deleteComment((int) $r['target_id'], Auth::id()),
            'group' => Auth::isAdmin() ? Groups::delete((int) $r['target_id']) : null,
            'page' => Auth::isAdmin() ? Pages::delete((int) $r['target_id']) : null,
            'user' => Auth::isAdmin() ? DB::update('users', ['banned' => 1], 'id = ? AND role <> ?', [$r['target_id'], 'admin']) : null,
            default => null,
        };
    }
    DB::update('reports', ['status' => in_array($action, ['remove', 'hide'], true) ? 'resolved' : 'dismissed'], "target_type = ? AND target_id = ? AND status = 'open'", [$r['target_type'], $r['target_id']]);
    flash('success', t('admin.done'));
    redirect('admin/reports');
}, $modOnly);

Router::get('/admin/groups', function () {
    View::render('admin/groups', ['noAside' => true, 'title' => t('admin.groups'), 'nav' => 'admin', 'anav' => 'groups',
        'groups' => DB::all('SELECT * FROM {communities} ORDER BY id DESC LIMIT 200')]);
}, $adminOnly);

Router::get('/admin/settings', function () {
    View::render('admin/settings', ['noAside' => true, 'title' => t('admin.settings'), 'nav' => 'admin', 'anav' => 'settings']);
}, $adminOnly);

Router::post('/admin/settings', function () {
    $text = ['site_name' => 80, 'site_description' => 300, 'mail_from' => 190, 'mail_from_name' => 80, 'smtp_host' => 190, 'smtp_user' => 190, 'terms_text' => 30000, 'privacy_text' => 30000,
        'landing_title' => 120, 'landing_lead' => 300, 'maintenance_text' => 1000];
    foreach ($text as $k => $max) {
        setting_set($k, Text::clean((string) input($k, ''), $max));
    }
    if (trim((string) setting('site_name', '')) === '') {
        setting_set('site_name', 'Zinkly Social Lite');
    }
    foreach (['registration_open', 'stories_enabled', 'groups_enabled', 'messages_enabled', 'pages_enabled', 'comments_enabled', 'rich_text', 'anonymous_posts',
        'extensions_enabled', 'animations', 'landing_features', 'landing_stats', 'maintenance', 'noindex', 'force_default_lang'] as $k) {
        setting_set($k, input($k) ? '1' : '0');
    }
    setting_set('default_lang', isset(Lang::LANGS[input('default_lang')]) ? input('default_lang') : 'en');
    setting_set('landing_mode', in_array(input('landing_mode'), ['landing', 'login', 'explore'], true) ? input('landing_mode') : 'landing');
    setting_set('accent', in_array(input('accent'), ['violet', 'blue', 'green', 'pink', 'orange', 'red'], true) ? input('accent') : 'violet');
    setting_set('guest_theme', in_array(input('guest_theme'), ['dark', 'light', 'system'], true) ? input('guest_theme') : 'system');
    setting_set('mail_method', in_array(input('mail_method'), ['smtp', 'php', 'off'], true) ? input('mail_method') : 'php');
    setting_set('smtp_encryption', in_array(input('smtp_encryption'), ['tls', 'ssl', 'none'], true) ? input('smtp_encryption') : 'tls');
    setting_set('smtp_port', (string) max(1, min(65535, (int) input('smtp_port', 587))));
    // the password field is always shown empty: typing a new one replaces it, leaving it empty keeps it
    $pass = is_string($_POST['smtp_pass'] ?? null) ? substr($_POST['smtp_pass'], 0, 300) : '';
    if ($pass !== '') {
        setting_set('smtp_pass', $pass);
    } elseif (input('smtp_pass_clear')) {
        setting_set('smtp_pass', '');
    }
    setting_set('max_upload_mb', (string) max(1, min(64, (int) input('max_upload_mb', 8))));
    setting_set('max_post_images', (string) max(1, min(10, (int) input('max_post_images', 4))));
    setting_set('max_post_chars', (string) max(100, min(20000, (int) input('max_post_chars', 5000))));
    setting_set('story_video', input('story_video') ? '1' : '0');
    setting_set('story_video_mb', (string) max(1, min(200, (int) input('story_video_mb', 20))));
    setting_set('story_video_seconds', (string) max(5, min(600, (int) input('story_video_seconds', 60))));
    $files = Upload::files('logo');
    if ($files) {
        try {
            $img = Upload::image($files[0], 'site', 256, null, true);
            Upload::remove(setting('logo'));
            setting_set('logo', $img['path']);
        } catch (RuntimeException $e) {
            flash('error', $e->getMessage());
            redirect('admin/settings');
        }
    } elseif (input('remove_logo')) {
        Upload::remove(setting('logo'));
        setting_set('logo', null);
    }
    flash('success', t('settings.saved'));
    redirect('admin/settings' . (preg_match('/^[a-z]+$/', (string) input('_section', '')) ? '#' . input('_section') : ''));
}, $adminOnly);

// checks the email settings typed on the page (saved or not); with "to", also sends a real test email
Router::post('/admin/settings/mail-test', function () {
    if (!RateLimit::hit('mail-test:' . Auth::id(), 20, 600)) {
        json_out(['ok' => false, 'message' => t('auth.too_many'), 'checks' => []], 429);
    }
    $in = [];
    foreach (['mail_method', 'mail_from', 'mail_from_name', 'smtp_host', 'smtp_port', 'smtp_encryption', 'smtp_user'] as $k) {
        $in[$k] = (string) input($k, '');
    }
    $in['smtp_pass'] = is_string($_POST['smtp_pass'] ?? null) ? substr($_POST['smtp_pass'], 0, 300) : '';
    $to = null;
    if (input('send')) {
        $to = (string) input('test_to', '');
        if (!filter_var($to, FILTER_VALIDATE_EMAIL)) {
            json_out(['ok' => false, 'message' => t('auth.email_invalid'), 'checks' => []]);
        }
    }
    @set_time_limit(60);
    $res = Mailer::test(Mailer::config($in), $to);
    json_out($res);
}, $adminOnly);

/* ---------- pages (brands, artists ...) ---------- */

Router::get('/admin/pages', function () {
    $q = Text::clean((string) input('q', ''), 60);
    View::render('admin/pages', ['noAside' => true, 'title' => t('admin.pages_social'), 'nav' => 'admin', 'anav' => 'pages', 'q' => $q,
        'rows' => Pages::list($q, '', 200), 'owners' => Users::many(array_column(DB::all('SELECT owner_id FROM {pages}'), 'owner_id'))]);
}, $adminOnly);

Router::post('/admin/pages/{id}/verify', function ($id) {
    $pg = Pages::find((int) $id);
    if ($pg) {
        DB::update('pages', ['verified' => $pg['verified'] ? 0 : 1], 'id = ?', [$pg['id']]);
        flash('success', t('admin.done'));
    }
    redirect('admin/pages');
}, $adminOnly);

/* ---------- site pages (About, Contact ...) ---------- */

Router::get('/admin/site-pages', function () {
    $edit = (int) input('edit', 0);
    View::render('admin/site-pages', ['noAside' => true, 'title' => t('admin.site_pages'), 'nav' => 'admin', 'anav' => 'site-pages',
        'rows' => SitePages::all(), 'edit' => $edit ? SitePages::find($edit) : null, 'new' => input('new') !== null]);
}, $adminOnly);

Router::post('/admin/site-pages', function () {
    try {
        $id = SitePages::save((int) input('id', 0), $_POST);
    } catch (RuntimeException $e) {
        flash('error', $e->getMessage());
        back();
    }
    flash('success', t('settings.saved'));
    redirect('admin/site-pages?edit=' . $id);
}, $adminOnly);

Router::post('/admin/site-pages/{id}/delete', function ($id) {
    SitePages::delete((int) $id);
    flash('success', t('admin.done'));
    redirect('admin/site-pages');
}, $adminOnly);

/* ---------- extensions ---------- */

Router::get('/admin/extensions', function () {
    $open = (string) input('ext', '');
    View::render('admin/extensions', ['noAside' => true, 'title' => t('admin.extensions'), 'nav' => 'admin', 'anav' => 'extensions',
        'list' => Ext::all(), 'open' => preg_match(Ext::ID_RE, $open) ? $open : '',
        'canZip' => class_exists('ZipArchive'), 'writable' => is_dir(Ext::dir()) ? is_writable(Ext::dir()) : is_writable(ZL_ROOT)]);
}, $adminOnly);

Router::post('/admin/extensions', function () {
    RateLimit::guard('ext-upload:' . Auth::id(), 20, 3600);
    try {
        $files = Upload::files('package');
        if (!$files) {
            throw new RuntimeException(t('ext.choose_file'));
        }
        $id = Ext::install($files[0]);
    } catch (RuntimeException $e) {
        flash('error', $e->getMessage());
        redirect('admin/extensions');
    }
    flash('success', t('ext.installed'));
    redirect('admin/extensions?ext=' . $id);
}, $adminOnly);

Router::post('/admin/extensions/{id}/{action}', function ($id, $action) {
    if (!Ext::row((string) $id)) {
        abort(404);
    }
    try {
        switch ($action) {
            case 'enable':
            case 'disable':
                Ext::enable((string) $id, $action === 'enable');
                flash('success', $action === 'enable' ? t('ext.enabled') : t('ext.disabled'));
                break;
            case 'settings':
                Ext::saveSettings((string) $id, (array) ($_POST['s'] ?? []), isset($_POST['license_key']) ? (string) $_POST['license_key'] : null);
                flash('success', t('settings.saved'));
                break;
            case 'delete':
                Ext::remove((string) $id);
                flash('success', t('ext.removed'));
                redirect('admin/extensions');
            default:
                abort(404);
        }
    } catch (RuntimeException $e) {
        flash('error', $e->getMessage());
    }
    redirect('admin/extensions?ext=' . $id);
}, $adminOnly);

/* ---------- updates, changelog, backups ---------- */

$passwordOk = fn (): bool => password_verify((string) input('password', ''), (string) (Auth::user()['password'] ?? ''));

Router::get('/admin/updates', function () {
    $tab = in_array(input('tab'), ['update', 'changelog', 'backups'], true) ? (string) input('tab') : 'update';
    $free = @disk_free_space(ZL_ROOT);
    View::render('admin/updates', ['noAside' => true, 'title' => t('admin.updates'), 'nav' => 'admin', 'anav' => 'updates', 'tab' => $tab,
        'pending' => Updater::pending(), 'last' => Updater::last(), 'backups' => $tab === 'backups' ? Backup::all() : [], 'problem' => Backup::available(),
        'free' => $free === false ? null : (int) $free, 'maxUpload' => (int) ini_get('upload_max_filesize')]);
}, $adminOnly);

Router::post('/admin/updates/upload', function () {
    RateLimit::guard('update-upload:' . Auth::id(), 20, 3600);
    try {
        $files = Upload::files('package');
        if (!$files) {
            throw new RuntimeException(t('ext.choose_file'));
        }
        $info = Updater::upload($files[0]);
        flash($info['newer'] ? 'success' : 'error', $info['newer'] ? t('update.ready', ['v' => $info['version']]) : ($info['same'] ? t('update.same') : t('update.older', ['v' => $info['version']])));
    } catch (RuntimeException $e) {
        flash('error', $e->getMessage());
    }
    redirect('admin/updates');
}, $adminOnly);

Router::post('/admin/updates/apply', function () use ($passwordOk) {
    if (!$passwordOk()) {
        flash('error', t('settings.wrong_password'));
        redirect('admin/updates');
    }
    try {
        $r = Updater::apply((bool) input('reinstall'));
    } catch (RuntimeException $e) {
        flash('error', $e->getMessage());
        redirect('admin/updates');
    }
    // the next page loads the new program, which upgrades the database by itself
    flash('success', t('update.done', ['from' => $r['from'], 'to' => $r['to'], 'n' => $r['files']]));
    redirect('admin/updates?updated=1');
}, $adminOnly);

Router::post('/admin/updates/cancel', function () {
    Updater::clearPending();
    flash('success', t('update.cancelled'));
    redirect('admin/updates');
}, $adminOnly);

Router::post('/admin/backups', function () {
    RateLimit::guard('backup:' . Auth::id(), 20, 3600);
    try {
        $name = Backup::create(array_keys(array_filter((array) ($_POST['parts'] ?? []))), 'manual');
        flash('success', t('backup.created', ['name' => $name, 'size' => Backup::human((int) filesize(Backup::path($name)))]));
    } catch (RuntimeException $e) {
        flash('error', $e->getMessage());
    }
    redirect('admin/updates?tab=backups');
}, $adminOnly);

Router::get('/admin/backups/{name}/download', function ($name) {
    if (!Backup::valid($name)) {
        abort(404);
    }
    $f = Backup::path($name);
    while (ob_get_level()) {
        ob_end_clean();
    }
    header('Content-Type: application/zip');
    header('Content-Disposition: attachment; filename="' . basename($f) . '"');
    header('Content-Length: ' . filesize($f));
    header('Cache-Control: no-store');
    readfile($f);
    exit;
}, $adminOnly);

Router::post('/admin/backups/{name}/{action}', function ($name, $action) use ($passwordOk) {
    if (!Backup::valid($name)) {
        abort(404);
    }
    if ($action === 'delete') {
        Backup::delete($name);
        flash('success', t('backup.deleted'));
        redirect('admin/updates?tab=backups');
    }
    if ($action !== 'restore') {
        abort(404);
    }
    if (!$passwordOk()) {
        flash('error', t('settings.wrong_password'));
        redirect('admin/updates?tab=backups');
    }
    try {
        // first a backup of how things are now, so a restore can always be undone
        $safety = Backup::create(['database', 'files'], 'before-restore');
        $done = Backup::restore($name, (bool) input('with_uploads'));
        flash('success', t('backup.restored', ['name' => $name, 'safety' => $safety]));
    } catch (RuntimeException $e) {
        flash('error', $e->getMessage());
    }
    redirect('admin/updates?tab=backups');
}, $adminOnly);
