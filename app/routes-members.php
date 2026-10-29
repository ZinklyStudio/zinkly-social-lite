<?php
/** Zinkly Social Lite - Admin › member page: account, password, security (IP, devices) and content tools. Loaded by routes.php. */

$adminOnly = ['auth' => true, 'admin' => true];
$memberOr404 = function ($id) {
    $u = Users::find((int) $id);
    if (!$u) {
        abort(404);
    }
    return $u;
};
$postOr404 = function ($id) {
    $p = Posts::find((int) $id);
    if (!$p) {
        abort(404);
    }
    return $p;
};
// a form in the member page or the post editor sends where to go back to
$backTo = function (string $fallback) {
    $to = (string) input('_back', '');
    redirect(preg_match('~^admin/[A-Za-z0-9_/\-]+(\?[A-Za-z0-9=&_\-]*)?(#[a-z]+)?$~', $to) ? $to : $fallback);
};

Router::get('/admin/users/{id}', function ($id) use ($memberOr404) {
    $u = $memberOr404($id);
    $tab = in_array(input('tab'), ['account', 'security', 'posts', 'media'], true) ? input('tab') : 'account';
    $data = ['noAside' => true, 'title' => '@' . $u['username'], 'nav' => 'admin', 'anav' => 'users', 'm' => $u, 'tab' => $tab,
        'counts' => ['posts' => (int) DB::value('SELECT COUNT(*) FROM {posts} WHERE user_id = ?', [$u['id']]),
            'images' => (int) DB::value('SELECT COUNT(*) FROM {post_images} i JOIN {posts} p ON p.id = i.post_id WHERE p.user_id = ?', [$u['id']]),
            'stories' => (int) DB::value('SELECT COUNT(*) FROM {stories} WHERE user_id = ? AND expires_at > ?', [$u['id'], time()]),
            'logins' => (int) DB::value('SELECT COUNT(*) FROM {login_log} WHERE user_id = ? AND ok = 1', [$u['id']]),
            'failed' => (int) DB::value('SELECT COUNT(*) FROM {login_log} WHERE user_id = ? AND ok = 0 AND created_at > ?', [$u['id'], time() - 7 * 86400])],
        'lastLogin' => DB::one('SELECT * FROM {login_log} WHERE user_id = ? AND ok = 1 ORDER BY id DESC LIMIT 1', [$u['id']])];
    if ($tab === 'security') {
        $data += ['history' => Security::history((int) $u['id'], 60), 'devices' => Security::devices((int) $u['id']), 'sameIp' => Security::sameIp((int) $u['id'])];
    } elseif ($tab === 'posts') {
        $page = max(1, (int) input('page', 1));
        $data += ['posts' => DB::all('SELECT * FROM {posts} WHERE user_id = ? ORDER BY id DESC LIMIT 20 OFFSET ' . (($page - 1) * 20), [$u['id']]), 'page' => $page];
        $ids = array_column($data['posts'], 'id');
        $data['thumbs'] = [];
        if ($ids) {
            foreach (DB::all('SELECT post_id, thumb FROM {post_images} WHERE post_id IN (' . DB::in($ids) . ') ORDER BY position', $ids) as $r) {
                $data['thumbs'][(int) $r['post_id']][] = $r['thumb'];
            }
        }
    } elseif ($tab === 'media') {
        $data += ['images' => DB::all('SELECT i.*, p.status FROM {post_images} i JOIN {posts} p ON p.id = i.post_id WHERE p.user_id = ? ORDER BY i.id DESC LIMIT 60', [$u['id']]),
            'stories' => DB::all('SELECT * FROM {stories} WHERE user_id = ? AND expires_at > ? ORDER BY id DESC', [$u['id'], time()]),
            'avatarCover' => array_filter(['avatar' => $u['avatar'], 'cover' => $u['cover']])];
    }
    View::render('admin/member', $data);
}, $adminOnly);

Router::post('/admin/users/{id}/account', function ($id) use ($memberOr404) {
    $u = $memberOr404($id);
    $username = trim((string) input('username', ''));
    $email = mb_strtolower(trim((string) input('email', '')));
    $err = ($username !== $u['username'] ? Users::usernameError($username, (int) $u['id']) : null)
        ?? (filter_var($email, FILTER_VALIDATE_EMAIL) ? null : t('auth.email_invalid'))
        ?? (DB::value('SELECT id FROM {users} WHERE LOWER(email) = ? AND id <> ?', [$email, $u['id']]) ? t('auth.email_taken') : null);
    if ($err) {
        flash('error', $err);
        redirect('admin/users/' . $u['id']);
    }
    DB::update('users', ['username' => $username, 'email' => $email, 'name' => Text::clean((string) input('name', ''), 80),
        'bio' => Text::clean((string) input('bio', ''), 300), 'verified' => input('verified') ? 1 : 0], 'id = ?', [$u['id']]);
    flash('success', t('member.saved'));
    redirect('admin/users/' . $u['id']);
}, $adminOnly);

Router::post('/admin/users/{id}/password', function ($id) use ($memberOr404) {
    $u = $memberOr404($id);
    $p = (string) ($_POST['password'] ?? '');
    if ($err = Auth::passwordError($p)) {
        flash('error', $err);
        redirect('admin/users/' . $u['id']);
    }
    DB::update('users', ['password' => password_hash($p, PASSWORD_DEFAULT), 'reset_token' => null, 'reset_expires' => null], 'id = ?', [$u['id']]);
    if (input('signout')) {
        Security::signOutEverywhere((int) $u['id']);
    }
    if (input('notify')) {
        Mailer::send($u['email'], t('member.pw_changed_subject', ['site' => site_name()]), t('member.pw_changed_mail', ['name' => display_name($u), 'site' => site_name(), 'link' => full_url('forgot')]));
    }
    flash('success', t('member.password_set'));
    redirect('admin/users/' . $u['id']);
}, $adminOnly);

Router::post('/admin/users/{id}/send-reset', function ($id) use ($memberOr404) {
    $u = $memberOr404($id);
    $token = bin2hex(random_bytes(24));
    DB::update('users', ['reset_token' => hash('sha256', $token), 'reset_expires' => time() + 86400], 'id = ?', [$u['id']]);
    $link = full_url('reset/' . $token);
    $sent = Mailer::send($u['email'], t('auth.reset_subject', ['site' => site_name()]), t('auth.reset_mail', ['name' => display_name($u), 'link' => $link, 'site' => site_name()]));
    // when email does not work, the admin gets the link to pass on another way
    $sent ? flash('success', t('member.reset_sent', ['email' => $u['email']])) : flash('error', t('member.reset_not_sent', ['link' => $link]));
    redirect('admin/users/' . $u['id']);
}, $adminOnly);

Router::post('/admin/users/{id}/signout', function ($id) use ($memberOr404) {
    $u = $memberOr404($id);
    Security::signOutEverywhere((int) $u['id']);
    flash('success', t('member.signed_out'));
    redirect('admin/users/' . $u['id'] . '?tab=security');
}, $adminOnly);

Router::post('/admin/users/{id}/clear-photo', function ($id) use ($memberOr404) {
    $u = $memberOr404($id);
    $which = input('which') === 'cover' ? 'cover' : 'avatar';
    Upload::remove($u[$which]);
    DB::update('users', [$which => null], 'id = ?', [$u['id']]);
    flash('success', t('admin.done'));
    redirect('admin/users/' . $u['id'] . '?tab=media');
}, $adminOnly);

/* ---------- content: edit a post, its photos, and stories ---------- */

Router::get('/admin/posts/{id}/edit', function ($id) use ($postOr404) {
    $p = $postOr404($id);
    View::render('admin/post-edit', ['noAside' => true, 'title' => t('member.edit_post'), 'nav' => 'admin', 'anav' => 'posts', 'p' => $p,
        'author' => Users::find((int) $p['user_id']), 'images' => DB::all('SELECT * FROM {post_images} WHERE post_id = ? ORDER BY position', [$p['id']])]);
}, $adminOnly);

Router::post('/admin/posts/{id}/edit', function ($id) use ($postOr404) {
    $p = $postOr404($id);
    try {
        Posts::edit((int) $p['id'], (string) input('body', ''), (string) input('privacy', $p['privacy']), (string) input('format', $p['format'] ?? 'text'));
        $files = Upload::files('images');
        if ($files) {
            Posts::addImages((int) $p['id'], $files);
        }
    } catch (RuntimeException $e) {
        flash('error', $e->getMessage());
        redirect('admin/posts/' . $p['id'] . '/edit');
    }
    flash('success', t('member.saved'));
    redirect('admin/posts/' . $p['id'] . '/edit');
}, $adminOnly);

Router::post('/admin/posts/{id}/{action}', function ($id, $action) use ($postOr404, $backTo) {
    $p = $postOr404($id);
    match ($action) {
        'hide' => Posts::setStatus((int) $p['id'], 'hidden', Auth::id()),
        'unhide' => Posts::setStatus((int) $p['id'], 'published', Auth::id()),
        'delete' => Posts::delete((int) $p['id']),
        default => abort(404),
    };
    flash('success', t('admin.done'));
    $backTo($action === 'delete' ? 'admin/users/' . $p['user_id'] . '?tab=posts' : 'admin/posts/' . $p['id'] . '/edit');
}, $adminOnly);

Router::post('/admin/images/{id}/{action}', function ($id, $action) use ($backTo) {
    $im = DB::one('SELECT * FROM {post_images} WHERE id = ?', [(int) $id]) ?? abort(404);
    try {
        if ($action === 'replace') {
            $f = Upload::files('image');
            if (!$f) {
                throw new RuntimeException(t('story.need_photo'));
            }
            Posts::replaceImage((int) $im['id'], $f[0]);
        } elseif ($action === 'remove') {
            Posts::removeImage((int) $im['id']);
        } else {
            abort(404);
        }
        flash('success', t('admin.done'));
    } catch (RuntimeException $e) {
        flash('error', $e->getMessage());
    }
    $backTo('admin/posts/' . $im['post_id'] . '/edit');
}, $adminOnly);

Router::post('/admin/stories/{id}/{action}', function ($id, $action) use ($backTo) {
    $s = DB::one('SELECT * FROM {stories} WHERE id = ?', [(int) $id]) ?? abort(404);
    try {
        if ($action === 'replace') {
            $f = Upload::files('media');
            if (!$f) {
                throw new RuntimeException(t('story.need_photo'));
            }
            Stories::replaceMedia((int) $s['id'], $f[0]);
        } elseif ($action === 'delete') {
            Stories::delete((int) $s['id'], Auth::id());
        } else {
            abort(404);
        }
        flash('success', t('admin.done'));
    } catch (RuntimeException $e) {
        flash('error', $e->getMessage());
    }
    $backTo('admin/users/' . $s['user_id'] . '?tab=media');
}, $adminOnly);
