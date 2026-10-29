<?php $logo = setting('logo'); $gt = (string) setting('guest_theme', 'system'); ?><!doctype html>
<html lang="<?= e(Lang::htmlLang()) ?>" class="<?= e($gt === 'light' ? 'light' : ($gt === 'dark' ? 'dark' : 'theme-system')) ?> accent-<?= e((string) setting('accent', 'violet')) ?>">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
<title><?= e(($title ?? '') !== '' && ($title ?? '') !== site_name() ? $title . ' · ' . site_name() : site_name()) ?></title>
<meta name="description" content="<?= e(setting('site_description') ?: t('landing.lead')) ?>">
<?php if (setting('noindex', '0') === '1'): ?><meta name="robots" content="noindex"><?php endif ?>
<meta property="og:site_name" content="<?= e(site_name()) ?>">
<meta property="og:title" content="<?= e($title ?? site_name()) ?>">
<meta name="csrf-token" content="<?= e(csrf_token()) ?>">
<meta name="color-scheme" content="dark light">
<link rel="icon" href="<?= e($logo ? upload_url($logo) : asset('assets/img/icon.svg')) ?>">
<link rel="stylesheet" href="<?= e(asset('assets/css/zl.css')) ?>">
<script src="<?= e(asset('assets/js/zl.js')) ?>" defer></script>
<?= Ext::html('head') ?>
</head>
<body class="zl-guest<?= setting('animations', '1') === '1' ? ' zl-anim' : '' ?>" data-base="<?= e(base_url()) ?>" data-texts="<?= e(json_encode(Lang::jsTexts(), JSON_UNESCAPED_UNICODE)) ?>">
<header class="zl-guest-top">
    <a class="zl-brand" href="<?= e(url('')) ?>"><span class="zl-logo"><?php if ($logo): ?><img src="<?= e(upload_url($logo)) ?>" alt=""><?php else: ?>Z<?php endif ?></span><b><?= e(site_name()) ?></b></a>
    <div class="zl-row">
        <?php if (!Auth::check()): ?>
            <?php if (setting('landing_mode', 'landing') !== 'login'): ?><a class="zl-btn zl-btn-soft zl-btn-sm zl-hide-sm" href="<?= e(url('explore')) ?>"><?= e(t('nav.explore')) ?></a><?php endif ?>
            <a class="zl-btn zl-btn-soft zl-btn-sm" href="<?= e(url('login')) ?>"><?= e(t('auth.login')) ?></a>
            <?php if (setting('registration_open', '1') === '1'): ?><a class="zl-btn zl-btn-main zl-btn-sm" href="<?= e(url('register')) ?>"><?= e(t('auth.register')) ?></a><?php endif ?>
        <?php else: ?>
            <a class="zl-btn zl-btn-soft zl-btn-sm" href="<?= e(url('')) ?>"><?= e(t('nav.home')) ?></a>
        <?php endif ?>
    </div>
</header>
<main id="main" style="flex:1;display:flex;flex-direction:column">
    <?php if ($m = flash('success')): ?><div class="zl-auth" style="padding-bottom:0;margin-bottom:0"><div class="zl-alert zl-alert-ok" role="status"><?= e($m) ?></div></div><?php endif ?>
    <?php if ($m = flash('error')): ?><div class="zl-auth" style="padding-bottom:0;margin-bottom:0"><div class="zl-alert zl-alert-error" role="alert"><?= e($m) ?></div></div><?php endif ?>
    <?= $content ?>
</main>
<footer class="zl-guest-foot">
    <?php foreach (SitePages::footer() as $fp): ?><a href="<?= e(url('info/' . $fp['slug'])) ?>"><?= e($fp['title']) ?></a><?php endforeach ?>
    <a href="<?= e(url('terms')) ?>"><?= e(t('page.terms')) ?></a>
    <a href="<?= e(url('privacy')) ?>"><?= e(t('page.privacy')) ?></a>
    <form method="post" action="<?= e(url('prefs')) ?>" class="zl-row"><?= csrf_field() ?>
        <label class="sr-only" for="glang"><?= e(t('settings.language')) ?></label>
        <select id="glang" name="lang" class="zl-input zl-input-sm" data-autosubmit><?php foreach (Lang::LANGS as $c => $n): ?><option value="<?= e($c) ?>" <?= $c === Lang::locale() ? 'selected' : '' ?>><?= e($n) ?></option><?php endforeach ?></select>
        <noscript><button class="zl-btn zl-btn-soft zl-btn-sm"><?= e(t('common.go')) ?></button></noscript>
    </form>
    <span>© <?= date('Y') ?> <?= e(site_name()) ?></span>
</footer>
<?= Ext::html('body_end', Auth::user()) ?>
</body>
</html>
