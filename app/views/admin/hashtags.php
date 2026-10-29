<?php /** Admin › Hashtags: the trending card in the right column. */ ?>
<div class="zl-settings zl-admin">
    <?php View::partial('admin-nav', ['anav' => $anav]); ?>
    <div class="zl-stack">
        <div class="zl-card zl-pad"><h1 class="zl-h1"><?= icon('hash', 'zl-i-sm') ?> <?= e(t('admin.hashtags')) ?></h1><p class="zl-faint"><?= e(t('trend.admin_hint')) ?></p></div>
        <div class="zl-admin-grid">
            <form class="zl-card zl-pad zl-stack" method="post" action="<?= e(url('admin/hashtags')) ?>">
                <?= csrf_field() ?>
                <h2 class="zl-h2"><?= icon('settings', 'zl-i-sm') ?> <?= e(t('trend.settings')) ?></h2>
                <label class="zl-check"><span><?= e(t('trend.f_enabled')) ?><small><?= e(t('trend.f_enabled_hint')) ?></small></span><span class="zl-switch"><input type="checkbox" name="trending_enabled" value="1" <?= setting('trending_enabled', '1') === '1' ? 'checked' : '' ?>><span></span></span></label>
                <div class="zl-grid2">
                    <label class="zl-field"><span><?= e(t('trend.limit')) ?></span><input class="zl-input" type="number" name="trending_limit" min="1" max="20" value="<?= (int) setting('trending_limit', '6') ?>"><small class="zl-hint"><?= e(t('trend.limit_hint')) ?></small></label>
                    <label class="zl-field"><span><?= e(t('trend.min')) ?></span><input class="zl-input" type="number" name="trending_min" min="1" max="1000" value="<?= (int) setting('trending_min', '1') ?>"><small class="zl-hint"><?= e(t('trend.min_hint')) ?></small></label>
                </div>
                <div class="zl-field"><span><?= e(t('trend.window')) ?></span>
                    <div class="zl-row"><input class="zl-input" type="number" name="trending_window" min="1" max="999" value="<?= (int) setting('trending_window', '7') ?>" style="width:110px" aria-label="<?= e(t('trend.window')) ?>">
                        <select class="zl-input" name="trending_unit" aria-label="<?= e(t('trend.unit')) ?>"><?php foreach (['hours', 'days', 'weeks'] as $un): ?><option value="<?= $un ?>" <?= setting('trending_unit', 'days') === $un ? 'selected' : '' ?>><?= e(t('trend.u_' . $un)) ?></option><?php endforeach ?></select></div>
                    <small class="zl-hint"><?= e(t('trend.window_hint')) ?></small></div>
                <button class="zl-btn zl-btn-main"><?= icon('check', 'zl-i-sm') ?><?= e(t('common.save')) ?></button>
            </form>
            <section class="zl-card zl-pad zl-stack">
                <h2 class="zl-h2"><?= icon('eye', 'zl-i-sm') ?> <?= e(t('trend.preview')) ?></h2>
                <?php if ($preview): ?><div class="zl-side-card" style="border:1px solid var(--border);border-radius:var(--radius-sm)"><?php foreach ($preview as $h): ?><div class="zl-trend"><span class="zl-tag">#<?= e($h['tag']) ?></span><small><?php if ($h['featured']): ?><span class="zl-chip-sm zl-chip-feat"><?= e(t('trend.featured')) ?></span> <?php endif ?><?= (int) $h['n'] ? e(tc('post.n_posts', (int) $h['n'])) : '' ?></small></div><?php endforeach ?></div>
                <?php else: ?><p class="zl-faint"><?= e(setting('trending_enabled', '1') === '1' ? t('trend.preview_empty') : t('trend.preview_off')) ?></p><?php endif ?>
            </section>
            <section class="zl-card zl-pad zl-stack zl-span-all">
                <h2 class="zl-h2"><?= icon('sparkle', 'zl-i-sm') ?> <?= e(t('trend.featured_title')) ?></h2>
                <p class="zl-faint"><?= e(t('trend.featured_hint')) ?></p>
                <form class="zl-row" method="post" action="<?= e(url('admin/hashtags/feature')) ?>" style="flex-wrap:wrap">
                    <?= csrf_field() ?>
                    <label class="sr-only" for="ft-tag"><?= e(t('trend.tag')) ?></label><input id="ft-tag" class="zl-input" name="tag" placeholder="#summer" required style="flex:1;min-width:150px">
                    <span class="zl-faint"><?= e(t('trend.for')) ?></span>
                    <label class="sr-only" for="ft-for"><?= e(t('trend.for')) ?></label><input id="ft-for" class="zl-input" type="number" name="for" min="0" max="999" value="7" style="width:90px">
                    <label class="sr-only" for="ft-unit"><?= e(t('trend.unit')) ?></label><select id="ft-unit" class="zl-input" name="unit" style="width:auto"><?php foreach (['hours', 'days', 'weeks'] as $un): ?><option value="<?= $un ?>" <?= $un === 'days' ? 'selected' : '' ?>><?= e(t('trend.u_' . $un)) ?></option><?php endforeach ?></select>
                    <button class="zl-btn zl-btn-main zl-btn-sm"><?= icon('plus', 'zl-i-sm') ?><?= e(t('trend.add')) ?></button>
                </form>
                <small class="zl-hint"><?= e(t('trend.for_hint')) ?></small>
                <?php foreach ($featured as $f): $exp = !empty($f['until']) && (int) $f['until'] <= time(); ?>
                    <div class="zl-between zl-trend-row"><span><span class="zl-tag">#<?= e($f['tag']) ?></span> <small class="zl-faint"><?= e(empty($f['until']) ? t('trend.always') : ($exp ? t('trend.expired') : t('trend.until', ['date' => Lang::date((int) $f['until']) . ' ' . date('H:i', (int) $f['until'])]))) ?></small></span>
                        <form method="post" action="<?= e(url('admin/hashtags/unfeature')) ?>"><?= csrf_field() ?><input type="hidden" name="tag" value="<?= e($f['tag']) ?>"><button class="zl-btn zl-btn-soft zl-btn-sm"><?= icon('x', 'zl-i-sm') ?><?= e(t('trend.remove')) ?></button></form></div>
                <?php endforeach ?>
            </section>
            <section class="zl-card zl-span-all">
                <div class="zl-pad"><h2 class="zl-h2"><?= icon('hash', 'zl-i-sm') ?> <?= e(t('trend.recent')) ?></h2><p class="zl-faint"><?= e(t('trend.recent_hint')) ?></p></div>
                <div class="zl-table-wrap"><table class="zl-table">
                    <thead><tr><th><?= e(t('trend.tag')) ?></th><th><?= e(t('trend.posts')) ?></th><th><?= e(t('trend.last_used')) ?></th><th><span class="sr-only"><?= e(t('admin.actions')) ?></span></th></tr></thead>
                    <tbody><?php foreach ($top as $h): $isHidden = in_array($h['tag'], $hidden, true); ?><tr>
                        <td><a class="zl-tag" href="<?= e(url('hashtag/' . rawurlencode($h['tag']))) ?>">#<?= e($h['tag']) ?></a><?php if ($isHidden): ?> <span class="zl-pill is-bad"><?= e(t('trend.hidden')) ?></span><?php endif ?></td>
                        <td><?= (int) $h['posts_count'] ?></td><td><?= $h['last_used'] ? e(ago((int) $h['last_used'])) : '—' ?></td>
                        <td><form method="post" action="<?= e(url('admin/hashtags/' . ($isHidden ? 'show' : 'hide'))) ?>" style="text-align:right"><?= csrf_field() ?><input type="hidden" name="tag" value="<?= e($h['tag']) ?>"><button class="zl-btn zl-btn-soft zl-btn-sm"><?= icon($isHidden ? 'eye' : 'eye-off', 'zl-i-sm') ?><?= e($isHidden ? t('trend.show') : t('trend.hide')) ?></button></form></td>
                    </tr><?php endforeach ?>
                    <?php if (!$top): ?><tr><td colspan="4"><div class="zl-empty"><?= icon('hash') ?><b><?= e(t('trend.none')) ?></b></div></td></tr><?php endif ?></tbody>
                </table></div>
            </section>
        </div>
    </div>
</div>
