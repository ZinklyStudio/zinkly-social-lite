<?php
/** Zinkly Social Lite - Pro membership pages, Admin › Pro and Admin › Hashtags. Loaded by routes.php. */

$proOn = function () {
    if (!Pro::enabled()) {
        abort(404);
    }
};
$myPayment = function () {
    $p = DB::one('SELECT * FROM {pro_payments} WHERE id = ? AND user_id = ?', [(int) input('pid', 0), Auth::id()]);
    if (!$p) {
        abort(404);
    }
    return $p;
};

Router::get('/pro', function () use ($proOn) {
    $proOn();
    $u = Auth::user();
    View::render('pages/pro', ['title' => t('pro.title'), 'nav' => 'pro', 'plans' => Pro::plans(), 'gateways' => Pro::gateways(),
        'pending' => DB::all("SELECT * FROM {pro_payments} WHERE user_id = ? AND status = 'pending' AND gateway = 'custom' ORDER BY id DESC LIMIT 5", [(int) $u['id']]),
        'isPro' => Pro::isPro($u), 'proUntil' => (int) ($u['pro_until'] ?? 0)]);
}, ['auth' => true]);

Router::post('/pro/checkout', function () use ($proOn) {
    $proOn();
    RateLimit::guard('pro:' . Auth::id(), 10, 600);
    $plan = Pro::plan((int) input('plan_id', 0));
    if (!$plan) {
        flash('error', t('pro.pick_plan'));
        redirect('pro');
    }
    try {
        $to = Pro::checkout(Auth::id(), $plan, (string) input('gateway', ''), (string) input('note', ''));
    } catch (RuntimeException $e) {
        flash('error', $e->getMessage());
        redirect('pro');
    }
    if ($to === null) {
        flash('success', t('pro.custom_sent'));
        redirect('pro');
    }
    header('Location: ' . $to, true, 303); // to Stripe or PayPal
    exit;
}, ['auth' => true]);

Router::get('/pro/return/{gateway}', function ($gateway) use ($proOn, $myPayment) {
    $proOn();
    $p = $myPayment();
    try {
        $ok = Pro::verify((int) $p['id']);
    } catch (Throwable $e) {
        @file_put_contents(ZL_ROOT . '/data/error.log', date('c') . ' [pro] verify ' . $p['id'] . ': ' . $e->getMessage() . "\n", FILE_APPEND | LOCK_EX);
        $ok = false;
    }
    flash($ok ? 'success' : 'error', $ok ? t('pro.thanks') : t('pro.not_paid'));
    redirect('pro');
}, ['auth' => true]);

Router::get('/pro/cancel', function () use ($myPayment) {
    $p = $myPayment();
    DB::update('pro_payments', ['status' => 'canceled'], "id = ? AND status = 'pending'", [$p['id']]);
    flash('error', t('pro.canceled'));
    redirect('pro');
}, ['auth' => true]);

/* ---------- Admin › Pro ---------- */

Router::get('/admin/pro', function () {
    $f = in_array(input('status'), ['pending', 'paid', 'failed', 'canceled', 'rejected'], true) ? input('status') : '';
    $pays = DB::all('SELECT * FROM {pro_payments}' . ($f ? ' WHERE status = ?' : '') . ' ORDER BY id DESC LIMIT 100', $f ? [$f] : []);
    View::render('admin/pro', ['noAside' => true, 'title' => t('admin.pro'), 'nav' => 'admin', 'anav' => 'pro', 'filter' => $f,
        'plans' => Pro::plans(false), 'payments' => $pays, 'payUsers' => Users::many(array_column($pays, 'user_id')),
        'members' => DB::all('SELECT * FROM {users} WHERE pro_until > ? ORDER BY pro_until DESC LIMIT 100', [time()]),
        'editPlan' => input('plan') ? Pro::plan((int) input('plan'), false) : null,
        'stats' => ['members' => (int) DB::value('SELECT COUNT(*) FROM {users} WHERE pro_until > ?', [time()]),
            'pending' => (int) DB::value("SELECT COUNT(*) FROM {pro_payments} WHERE status = 'pending' AND gateway = 'custom'"),
            'paid30' => DB::all("SELECT currency, SUM(amount_cents) AS total FROM {pro_payments} WHERE status = 'paid' AND paid_at > ? GROUP BY currency", [time() - 30 * 86400])]]);
}, ['auth' => true, 'admin' => true]);

Router::post('/admin/pro/settings', function () {
    foreach (['pro_enabled', 'pro_sidebar', 'pro_badge', 'pay_stripe', 'pay_paypal', 'pay_custom'] as $k) {
        setting_set($k, input($k) ? '1' : '0');
    }
    foreach (['pro_title' => 80, 'pro_text' => 300, 'custom_title' => 80, 'custom_instructions' => 3000, 'paypal_client' => 190] as $k => $max) {
        setting_set($k, Text::clean((string) input($k, ''), $max));
    }
    setting_set('paypal_mode', input('paypal_mode') === 'live' ? 'live' : 'sandbox');
    setting_set('pro_upload_mb', (string) max(0, min(200, (int) input('pro_upload_mb', 0))));
    // secret keys: shown empty, typing replaces, empty keeps (like the SMTP password)
    foreach (['stripe_secret', 'paypal_secret'] as $k) {
        $v = is_string($_POST[$k] ?? null) ? trim(substr($_POST[$k], 0, 300)) : '';
        if ($v !== '') {
            setting_set($k, $v);
        } elseif (input($k . '_clear')) {
            setting_set($k, '');
        }
    }
    flash('success', t('settings.saved'));
    redirect('admin/pro');
}, ['auth' => true, 'admin' => true]);

Router::post('/admin/pro/plans', function () {
    try {
        Pro::savePlan((int) input('id', 0), $_POST);
    } catch (RuntimeException $e) {
        flash('error', $e->getMessage());
        redirect('admin/pro#plans');
    }
    flash('success', t('settings.saved'));
    redirect('admin/pro#plans');
}, ['auth' => true, 'admin' => true]);

Router::post('/admin/pro/plans/{id}/delete', function ($id) {
    // plans with payments are only switched off, so the payment history keeps its plan
    if (DB::value('SELECT COUNT(*) FROM {pro_payments} WHERE plan_id = ?', [(int) $id])) {
        DB::update('pro_plans', ['active' => 0], 'id = ?', [(int) $id]);
    } else {
        DB::delete('pro_plans', 'id = ?', [(int) $id]);
    }
    flash('success', t('settings.saved'));
    redirect('admin/pro#plans');
}, ['auth' => true, 'admin' => true]);

Router::post('/admin/pro/payments/{id}/{action}', function ($id, $action) {
    $p = DB::one('SELECT * FROM {pro_payments} WHERE id = ?', [(int) $id]);
    if (!$p) {
        abort(404);
    }
    if ($action === 'approve' && in_array($p['status'], ['pending', 'rejected', 'canceled'], true)) {
        Pro::markPaid($p);
        flash('success', t('pro.approved'));
    } elseif ($action === 'reject' && $p['status'] === 'pending') {
        DB::update('pro_payments', ['status' => 'rejected'], 'id = ?', [$p['id']]);
        flash('success', t('settings.saved'));
    } elseif ($action === 'check') {
        try {
            $ok = Pro::verify((int) $p['id']);
            flash($ok ? 'success' : 'error', $ok ? t('pro.approved') : t('pro.not_paid'));
        } catch (Throwable $e) {
            flash('error', t('pro.gw_error') . ' ' . $e->getMessage());
        }
    }
    redirect('admin/pro#payments');
}, ['auth' => true, 'admin' => true]);

Router::post('/admin/pro/grant', function () {
    $u = Users::byUsername(ltrim(trim((string) input('username', '')), '@'));
    if (!$u) {
        flash('error', t('pro.no_user'));
        redirect('admin/pro#members');
    }
    $days = (int) input('days', 30);
    Pro::grant((int) $u['id'], input('revoke') ? 0 : max(1, min(3650, $days)));
    flash('success', t('settings.saved'));
    redirect('admin/pro#members');
}, ['auth' => true, 'admin' => true]);

/* ---------- Admin › Hashtags (the trending card) ---------- */

Router::get('/admin/hashtags', function () {
    View::render('admin/hashtags', ['noAside' => true, 'title' => t('admin.hashtags'), 'nav' => 'admin', 'anav' => 'hashtags',
        'top' => DB::all('SELECT * FROM {hashtags} ORDER BY last_used DESC LIMIT 50'), 'featured' => Posts::featuredTags(true),
        'hidden' => Posts::hiddenTags(), 'preview' => Posts::sidebarTags()]);
}, ['auth' => true, 'admin' => true]);

Router::post('/admin/hashtags', function () {
    setting_set('trending_enabled', input('trending_enabled') ? '1' : '0');
    setting_set('trending_limit', (string) max(1, min(20, (int) input('trending_limit', 6))));
    setting_set('trending_window', (string) max(1, min(999, (int) input('trending_window', 7))));
    setting_set('trending_unit', in_array(input('trending_unit'), ['hours', 'days', 'weeks'], true) ? input('trending_unit') : 'days');
    setting_set('trending_min', (string) max(1, min(1000, (int) input('trending_min', 1))));
    flash('success', t('settings.saved'));
    redirect('admin/hashtags');
}, ['auth' => true, 'admin' => true]);

Router::post('/admin/hashtags/feature', function () {
    $tag = mb_strtolower(trim((string) input('tag', ''), " #\t\n\r"));
    if (!preg_match('/^[\p{L}\p{N}_]{1,64}$/u', $tag)) {
        flash('error', t('trend.bad_tag'));
        redirect('admin/hashtags');
    }
    $n = max(0, min(999, (int) input('for', 0)));
    $unit = ['hours' => 3600, 'days' => 86400, 'weeks' => 604800][input('unit')] ?? 86400;
    $list = array_values(array_filter(Posts::featuredTags(true), fn ($f) => $f['tag'] !== $tag));
    $list[] = ['tag' => $tag, 'until' => $n ? time() + $n * $unit : 0];
    setting_set('trending_featured', json_encode($list, JSON_UNESCAPED_UNICODE));
    flash('success', t('settings.saved'));
    redirect('admin/hashtags');
}, ['auth' => true, 'admin' => true]);

Router::post('/admin/hashtags/{action}', function ($action) {
    $tag = mb_strtolower(trim((string) input('tag', ''), " #\t\n\r"));
    if ($action === 'unfeature') {
        setting_set('trending_featured', json_encode(array_values(array_filter(Posts::featuredTags(true), fn ($f) => $f['tag'] !== $tag)), JSON_UNESCAPED_UNICODE));
    } elseif ($action === 'hide' || $action === 'show') {
        $hidden = array_values(array_diff(Posts::hiddenTags(), [$tag]));
        if ($action === 'hide' && $tag !== '') {
            $hidden[] = $tag;
        }
        setting_set('trending_hidden', implode(',', $hidden));
    } else {
        abort(404);
    }
    flash('success', t('settings.saved'));
    redirect('admin/hashtags');
}, ['auth' => true, 'admin' => true]);
