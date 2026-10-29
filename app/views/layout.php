<?php
$u = Auth::user();
$nav = $nav ?? '';
$theme = $u['theme'] ?? (string) setting('guest_theme', 'system');
$themeClass = $theme === 'light' ? 'light' : ($theme === 'dark' ? 'dark' : 'theme-system');
$unreadN = $u ? Notify::unread((int) $u['id']) : 0;
$unreadM = ($u && setting('messages_enabled', '1') === '1') ? Messages::unreadTotal((int) $u['id']) : 0;
$logo = setting('logo');
$links = [
    ['home', '', 'home', t('nav.home'), 0],
    ['explore', 'explore', 'compass', t('nav.explore'), 0],
    ['notifications', 'notifications', 'bell', t('nav.notifications'), $unreadN],
];
if (setting('messages_enabled', '1') === '1') {
    $links[] = ['messages', 'messages', 'chat', t('nav.messages'), $unreadM];
}
if (setting('groups_enabled', '1') === '1') {
    $links[] = ['groups', 'groups', 'users', t('nav.groups'), 0];
}
if (Pages::enabled()) {
    $links[] = ['pages', 'pages', 'page', t('nav.pages'), 0];
}
$links[] = ['saved', 'saved', 'bookmark', t('nav.saved'), 0];
if ($u) {
    $links[] = ['profile', 'u/' . $u['username'], 'user', t('nav.profile'), 0];
}
$links[] = ['settings', 'settings', 'settings', t('nav.settings'), 0];
if (Auth::isMod()) {
    $links[] = ['admin', Auth::isAdmin() ? 'admin' : 'admin/posts', 'shield', t('nav.admin'), 0];
}
if (!$u) { // visitors (Explore open to guests): only the public pages
    $links = array_values(array_filter($links, fn ($l) => in_array($l[0], ['home', 'explore', 'pages'], true)));
}
$links = Ext::filter('nav_links', $links, $u);
$accent = (string) setting('accent', 'violet');
$anim = setting('animations', '1') === '1';
?><!doctype html>
<html lang="<?= e(Lang::htmlLang()) ?>" class="<?= e($themeClass) ?> accent-<?= e($accent) ?>">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
<title><?= e(($title ?? '') !== '' && ($title ?? '') !== site_name() ? $title . ' · ' . site_name() : site_name()) ?></title>
<meta name="description" content="<?= e(($desc ?? '') !== '' ? $desc : (setting('site_description') ?: t('landing.lead'))) ?>">
<?php if (setting('noindex', '0') === '1'): ?><meta name="robots" content="noindex"><?php endif ?>
<meta property="og:site_name" content="<?= e(site_name()) ?>">
<meta property="og:title" content="<?= e($title ?? site_name()) ?>">
<?php if (!empty($ogImage)): ?><meta property="og:image" content="<?= e($ogImage) ?>"><?php endif ?>
<meta name="csrf-token" content="<?= e(csrf_token()) ?>">
<meta name="color-scheme" content="dark light">
<link rel="icon" href="<?= e($logo ? upload_url($logo) : asset('assets/img/icon.svg')) ?>">
<link rel="stylesheet" href="<?= e(asset('assets/css/zl.css')) ?>">
<script src="<?= e(asset('assets/js/zl.js')) ?>" defer></script>
<?= Ext::html('head') ?>
</head>
<body class="<?= $anim ? 'zl-anim' : '' ?>" data-base="<?= e(base_url()) ?>" data-badges="<?= e(url('api/badges')) ?>" data-texts="<?= e(json_encode(Lang::jsTexts(), JSON_UNESCAPED_UNICODE)) ?>">
<a class="sr-only" href="#main"><?= e(t('common.skip')) ?></a>
<div class="zl-app<?= !empty($noAside) ? ' no-aside' : '' ?>">
    <aside class="zl-side" aria-label="<?= e(t('nav.menu')) ?>">
        <a class="zl-brand" href="<?= e(url('')) ?>"><span class="zl-logo"><?php if ($logo): ?><img src="<?= e(upload_url($logo)) ?>" alt=""><?php else: ?>Z<?php endif ?></span><b><?= e(site_name()) ?></b></a>
        <nav class="zl-nav">
            <?php foreach ($links as [$key, $href, $ic, $label, $count]): ?>
                <a href="<?= e(url($href)) ?>" class="<?= $nav === $key ? 'is-on' : '' ?>" <?= $nav === $key ? 'aria-current="page"' : '' ?> <?= in_array($key, ['notifications', 'messages'], true) ? 'data-badge="' . e($key) . '"' : '' ?>>
                    <?= icon($ic) ?><span><?= e($label) ?></span><?php if ($count): ?><span class="zl-count"><?= $count > 99 ? '99+' : (int) $count ?></span><?php endif ?>
                </a>
            <?php endforeach ?>
        </nav>
        <?php if ($u): ?>
        <button type="button" class="zl-btn zl-btn-main" data-open="composer-modal"><?= icon('plus') ?><?= e(t('post.create')) ?></button>
        <?php else: ?>
        <div class="zl-guest-cta">
            <a class="zl-btn zl-btn-main zl-btn-block" href="<?= e(url('login')) ?>"><?= e(t('auth.login')) ?></a>
            <?php if (setting('registration_open', '1') === '1'): ?><a class="zl-btn zl-btn-soft zl-btn-block" href="<?= e(url('register')) ?>"><?= e(t('auth.register')) ?></a><?php endif ?>
        </div>
        <?php endif ?>
        <?php if ($u): ?>
        <div class="zl-me zl-menu">
            <?= avatar_html($u, 'sm') ?>
            <div class="zl-me-name"><b><?= e(display_name($u)) ?></b><small>@<?= e($u['username']) ?></small></div>
            <button type="button" class="zl-icon-btn" data-menu aria-haspopup="true" aria-expanded="false" aria-label="<?= e(t('nav.account_menu')) ?>"><?= icon('dots') ?></button>
            <div class="zl-menu-list" hidden style="top:auto;bottom:calc(100% + 6px)">
                <a href="<?= e(url('u/' . $u['username'])) ?>"><?= icon('user', 'zl-i-sm') ?><?= e(t('nav.profile')) ?></a>
                <a href="<?= e(url('settings')) ?>"><?= icon('settings', 'zl-i-sm') ?><?= e(t('nav.settings')) ?></a>
                <form method="post" action="<?= e(url('logout')) ?>"><?= csrf_field() ?><button type="submit"><?= icon('logout', 'zl-i-sm') ?><?= e(t('auth.logout')) ?></button></form>
            </div>
        </div>
        <?php endif ?>
    </aside>

    <div class="zl-main-wrap">
        <header class="zl-topbar-m">
            <a class="zl-brand" href="<?= e(url('')) ?>"><span class="zl-logo"><?php if ($logo): ?><img src="<?= e(upload_url($logo)) ?>" alt=""><?php else: ?>Z<?php endif ?></span><b><?= e(site_name()) ?></b></a>
            <div class="zl-row" style="gap:2px">
                <a class="zl-icon-btn" href="<?= e(url('search')) ?>" aria-label="<?= e(t('nav.search')) ?>"><?= icon('search') ?></a>
                <a class="zl-icon-btn" href="<?= e(url('notifications')) ?>" aria-label="<?= e(t('nav.notifications')) ?>" data-badge-dot="notifications" style="position:relative"><?= icon('bell') ?><?php if ($unreadN): ?><span class="zl-nav-dot"></span><?php endif ?></a>
                <div class="zl-menu">
                    <button type="button" class="zl-icon-btn" data-menu aria-haspopup="true" aria-expanded="false" aria-label="<?= e(t('nav.menu')) ?>"><?= icon('menu') ?></button>
                    <div class="zl-menu-list" hidden>
                        <?php foreach ($links as [$key, $href, $ic, $label, $count]): ?>
                            <a href="<?= e(url($href)) ?>"><?= icon($ic, 'zl-i-sm') ?><?= e($label) ?></a>
                        <?php endforeach ?>
                        <?php if ($u): ?><form method="post" action="<?= e(url('logout')) ?>"><?= csrf_field() ?><button type="submit"><?= icon('logout', 'zl-i-sm') ?><?= e(t('auth.logout')) ?></button></form>
                        <?php else: ?><a href="<?= e(url('login')) ?>"><?= icon('user', 'zl-i-sm') ?><?= e(t('auth.login')) ?></a><?php endif ?>
                    </div>
                </div>
            </div>
        </header>

        <main id="main" class="zl-main" tabindex="-1">
            <?php if ($m = flash('success')): ?><div class="zl-col"><div class="zl-alert zl-alert-ok" role="status"><?= e($m) ?></div></div><?php endif ?>
            <?php if ($m = flash('error')): ?><div class="zl-col"><div class="zl-alert zl-alert-error" role="alert"><?= e($m) ?></div></div><?php endif ?>
            <?php if (Auth::isAdmin() && setting('maintenance', '0') === '1'): ?><div class="zl-col"><div class="zl-alert zl-alert-warn" role="status"><?= icon('settings', 'zl-i-sm') ?> <?= e(t('maint.admin_note')) ?> <a href="<?= e(url('admin/settings#features')) ?>"><?= e(t('maint.turn_off')) ?></a></div></div><?php endif ?>
            <?= $content ?>
        </main>
    </div>

    <?php if (empty($noAside)): ?>
    <aside class="zl-aside" aria-label="<?= e(t('nav.discover')) ?>">
        <form class="zl-search" action="<?= e(url('search')) ?>" method="get" role="search" data-live-search="<?= e(url('api/search')) ?>">
            <?= icon('search') ?>
            <label class="sr-only" for="q-side"><?= e(t('nav.search')) ?></label>
            <input id="q-side" class="zl-input" name="q" placeholder="<?= e(t('search.placeholder')) ?>" autocomplete="off">
            <div class="zl-dd" hidden></div>
        </form>
        <?php View::partial('pro-card', ['u' => $u]); ?>
        <?php $tr = isset($trending) && is_array($trending) && (!$trending || isset($trending[0]['featured'])) ? $trending : Posts::sidebarTags(); if ($tr): ?>
        <section class="zl-card zl-side-card">
            <h3><?= e(t('explore.trending')) ?></h3>
            <?php foreach ($tr as $h): ?><a class="zl-trend" href="<?= e(url('hashtag/' . rawurlencode($h['tag']))) ?>"><span class="zl-tag">#<?= e($h['tag']) ?></span><small><?php if (!empty($h['featured'])): ?><span class="zl-chip-sm zl-chip-feat"><?= icon('sparkle', 'zl-i-xs') ?><?= e(t('trend.featured')) ?></span> <?php endif ?><?= (int) $h['n'] ? e(tc('post.n_posts', (int) $h['n'])) : '' ?></small></a><?php endforeach ?>
            <div style="height:8px"></div>
        </section>
        <?php endif ?>
        <?php $sg = $suggestions ?? ($u ? Users::suggestions((int) $u['id'], 4) : []); if ($sg): ?>
        <section class="zl-card zl-side-card">
            <h3><?= e(t('people.suggested')) ?></h3>
            <div class="zl-people"><?php foreach ($sg as $p) { View::partial('person', ['p' => $p, 'compact' => true]); } ?></div>
        </section>
        <?php endif ?>
        <?php $spg = ($u && Pages::enabled()) ? Pages::suggestions((int) $u['id'], 3) : []; if ($spg): ?>
        <section class="zl-card zl-side-card">
            <h3><?= e(t('pages.suggested')) ?></h3>
            <div class="zl-people"><?php foreach ($spg as $pg): ?>
                <div class="zl-person"><a href="<?= e(url('pages/' . $pg['slug'])) ?>"><?= Pages::avatar($pg, 'sm') ?></a>
                    <a class="zl-person-info" href="<?= e(url('pages/' . $pg['slug'])) ?>"><b><?= e($pg['name']) ?><?php if ($pg['verified']): ?> <?= icon('verified', 'zl-verified') ?><?php endif ?></b><small><?= e(t('pages.cat_' . $pg['category'])) ?> · <?= e(tc('pages.n_followers', (int) $pg['followers_count'])) ?></small></a>
                    <form method="post" action="<?= e(url('pages/' . $pg['slug'] . '/follow')) ?>" data-page-follow><?= csrf_field() ?><button class="zl-btn zl-btn-main zl-btn-sm" data-l-on="<?= e(t('pages.following')) ?>" data-l-off="<?= e(t('pages.follow')) ?>"><?= e(t('pages.follow')) ?></button></form></div>
            <?php endforeach ?></div>
        </section>
        <?php endif ?>
        <?php if (!$tr && !$sg && !$spg): ?>
        <section class="zl-card zl-side-card zl-welcome-card">
            <h3><?= e(t('aside.welcome', ['site' => site_name()])) ?></h3>
            <p><?= e(t('aside.welcome_text')) ?></p>
            <div class="zl-quick">
                <a href="<?= e(url('explore')) ?>"><?= icon('compass', 'zl-i-sm') ?><?= e(t('nav.explore')) ?></a>
                <?php if (setting('groups_enabled', '1') === '1' && $u): ?><a href="<?= e(url('groups')) ?>"><?= icon('users', 'zl-i-sm') ?><?= e(t('nav.groups')) ?></a><?php endif ?>
                <?php if (Pages::enabled()): ?><a href="<?= e(url('pages')) ?>"><?= icon('page', 'zl-i-sm') ?><?= e(t('nav.pages')) ?></a><?php endif ?>
                <?php if ($u): ?><a href="<?= e(url('settings')) ?>"><?= icon('user', 'zl-i-sm') ?><?= e(t('profile.edit')) ?></a><?php endif ?>
            </div>
        </section>
        <?php endif ?>
        <?= Ext::html('aside', $u) ?>
        <nav class="zl-foot-links" aria-label="<?= e(t('nav.footer')) ?>">
            <?php foreach (SitePages::footer() as $fp): ?><a href="<?= e(url('info/' . $fp['slug'])) ?>"><?= e($fp['title']) ?></a><?php endforeach ?>
            <a href="<?= e(url('terms')) ?>"><?= e(t('page.terms')) ?></a><a href="<?= e(url('privacy')) ?>"><?= e(t('page.privacy')) ?></a>
            <span>© <?= date('Y') ?> <?= e(site_name()) ?></span>
        </nav>
    </aside>
    <?php endif ?>
</div>

<?php if ($u): ?>
<nav class="zl-bottombar" aria-label="<?= e(t('nav.menu')) ?>">
    <a href="<?= e(url('')) ?>" class="<?= $nav === 'home' ? 'is-on' : '' ?>" aria-label="<?= e(t('nav.home')) ?>"><?= icon('home') ?></a>
    <a href="<?= e(url('explore')) ?>" class="<?= $nav === 'explore' ? 'is-on' : '' ?>" aria-label="<?= e(t('nav.explore')) ?>"><?= icon('compass') ?></a>
    <a href="#" class="zl-fab" data-open="composer-modal" aria-label="<?= e(t('post.create')) ?>"><?= icon('plus') ?></a>
    <?php if (setting('messages_enabled', '1') === '1'): ?>
    <a href="<?= e(url('messages')) ?>" class="<?= $nav === 'messages' ? 'is-on' : '' ?>" aria-label="<?= e(t('nav.messages')) ?>" data-badge="messages"><?= icon('chat') ?><?php if ($unreadM): ?><span class="zl-count"><?= $unreadM > 99 ? '99+' : (int) $unreadM ?></span><?php endif ?></a>
    <?php endif ?>
    <?php if ($u): ?><a href="<?= e(url('u/' . $u['username'])) ?>" class="<?= $nav === 'profile' ? 'is-on' : '' ?>" aria-label="<?= e(t('nav.profile')) ?>"><?= avatar_html($u, 'xs') ?></a><?php endif ?>
</nav>
<?php endif ?>

<?php if ($u) { View::partial('composer-modal'); View::partial('modals'); } ?>
<div class="zl-progress" aria-hidden="true"></div>
<button type="button" class="zl-to-top" data-to-top hidden aria-label="<?= e(t('common.to_top')) ?>"><?= icon('arrow-up') ?></button>
<?= Ext::html('body_end', $u) ?>
</body>
</html>
