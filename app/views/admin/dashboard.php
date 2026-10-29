<div class="zl-settings zl-admin">
    <?php View::partial('admin-nav', ['anav' => $anav]); ?>
    <div class="zl-stack">
        <div class="zl-card zl-pad zl-between" style="flex-wrap:wrap"><div><h1 class="zl-h1"><?= e(t('admin.dashboard')) ?></h1><p class="zl-faint">Zinkly Social Lite <?= e($env['version']) ?> · PHP <?= e($env['php']) ?> · <?= e($env['db'] === 'sqlite' ? 'SQLite' : 'MySQL') ?></p></div>
            <div class="zl-row" style="flex-wrap:wrap;gap:6px">
                <a class="zl-btn zl-btn-soft zl-btn-sm" href="<?= e(url('admin/posts')) ?>"><?= icon('file', 'zl-i-sm') ?><?= e(t('admin.posts')) ?></a>
                <a class="zl-btn zl-btn-soft zl-btn-sm" href="<?= e(url('admin/extensions')) ?>"><?= icon('puzzle', 'zl-i-sm') ?><?= e(t('admin.extensions')) ?></a>
                <a class="zl-btn zl-btn-main zl-btn-sm" href="<?= e(url('admin/settings')) ?>"><?= icon('settings', 'zl-i-sm') ?><?= e(t('admin.settings')) ?></a>
            </div></div>
        <div class="zl-stats">
            <?php foreach ([['users', $stats['users'], $stats['users_new']], ['online', $stats['online'], null], ['posts', $stats['posts'], $stats['posts_new']], ['reports_open', $stats['reports'], null],
                ['comments', $stats['comments'], null], ['groups', $stats['groups'], null], ['pages', $stats['pages'], null], ['messages', $stats['messages'], null],
                ['stories', $stats['stories'], null], ['hidden', $stats['hidden'], null], ['anonymous', $stats['anonymous'], null], ['extensions', $stats['extensions'], null]] as [$k, $n, $new]): ?>
                <div class="zl-card zl-stat"><small><?= e(t('admin.s_' . $k)) ?></small><b data-count="<?= (int) $n ?>"><?= e(short_number((int) $n)) ?></b><?php if ($new !== null): ?><em>+<?= (int) $new ?> <?= e(t('admin.today')) ?></em><?php endif ?></div>
            <?php endforeach ?>
        </div>
        <?php $max = max(1, max(array_map(fn ($d) => max($d['users'], $d['posts']), $chart))); ?>
        <div class="zl-card zl-pad">
            <h2 class="zl-h2"><?= e(t('admin.last14')) ?></h2>
            <p class="zl-faint"><?= e(t('admin.chart_posts')) ?></p>
            <div class="zl-bars" role="img" aria-label="<?= e(t('admin.chart_posts')) ?>"><?php foreach ($chart as $d): ?><div title="<?= e($d['label'] . ': ' . $d['posts']) ?>"><i style="height:<?= round($d['posts'] / $max * 100) ?>%"></i><small><?= e($d['label']) ?></small></div><?php endforeach ?></div>
            <p class="zl-faint" style="margin-top:16px"><?= e(t('admin.chart_users')) ?></p>
            <div class="zl-bars" role="img" aria-label="<?= e(t('admin.chart_users')) ?>"><?php foreach ($chart as $d): ?><div title="<?= e($d['label'] . ': ' . $d['users']) ?>"><i style="height:<?= round($d['users'] / $max * 100) ?>%"></i><small><?= e($d['label']) ?></small></div><?php endforeach ?></div>
        </div>
        <div class="zl-grid2">
            <div class="zl-card"><h2 class="zl-h2" style="padding:14px 16px 4px"><?= e(t('admin.newest_members')) ?></h2><div class="zl-people"><?php foreach ($latest as $p) { View::partial('person', ['p' => $p, 'compact' => true]); } ?></div></div>
            <div class="zl-card zl-pad"><h2 class="zl-h2"><?= e(t('admin.storage')) ?></h2><p class="zl-strong" style="font-size:24px;margin-top:6px"><?= e(number_format($uploadsBytes / 1048576, 1)) ?> MB</p><p class="zl-faint"><?= e(t('admin.storage_hint')) ?></p></div>
        </div>
    </div>
</div>
