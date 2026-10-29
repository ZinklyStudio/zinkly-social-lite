<?php
$items = Auth::isAdmin()
    ? ['dashboard' => ['admin', 'chart'], 'users' => ['admin/users', 'users'], 'posts' => ['admin/posts', 'file'], 'reports' => ['admin/reports', 'flag'],
        'groups' => ['admin/groups', 'users'], 'pages' => ['admin/pages', 'page', t('admin.pages_social')], 'site-pages' => ['admin/site-pages', 'type'],
        'pro' => ['admin/pro', 'crown', t('admin.pro')], 'hashtags' => ['admin/hashtags', 'hash', t('admin.hashtags')],
        'extensions' => ['admin/extensions', 'puzzle'], 'updates' => ['admin/updates', 'upload', t('admin.updates')], 'settings' => ['admin/settings', 'settings']]
    : ['posts' => ['admin/posts', 'file'], 'reports' => ['admin/reports', 'flag']];
$items = Ext::filter('admin_nav', $items);
$open = (int) DB::value("SELECT COUNT(*) FROM {reports} WHERE status = 'open'");
?>
<nav class="zl-card zl-settings-nav" aria-label="<?= e(t('nav.admin')) ?>">
    <?php foreach ($items as $k => $it): [$href, $ic] = $it; $label = $it[2] ?? t('admin.' . str_replace('-', '_', $k)); // extensions add [url, icon, label] ?>
        <a href="<?= e(url($href)) ?>" class="<?= $anav === $k ? 'is-on' : '' ?>" <?= $anav === $k ? 'aria-current="page"' : '' ?>><?= icon($ic, 'zl-i-sm') ?><?= e($label) ?><?php if ($k === 'reports' && $open): ?><span class="zl-count" style="margin-left:auto"><?= $open ?></span><?php endif ?></a>
    <?php endforeach ?>
</nav>
