<div class="zl-col zl-col-wide">
    <div class="zl-card zl-pad zl-between zl-hero-card">
        <div><h1 class="zl-h1"><?= e(t('nav.pages')) ?></h1><p class="zl-faint"><?= e(t('pages.intro')) ?></p></div>
        <?php if (Auth::check()): ?><button type="button" class="zl-btn zl-btn-main zl-btn-sm" data-open="page-modal"><?= icon('plus', 'zl-i-sm') ?><?= e(t('pages.create')) ?></button><?php endif ?>
    </div>
    <?php if ($mine): ?>
        <h2 class="zl-h2"><?= e(t('pages.yours')) ?></h2>
        <div class="zl-groups-grid"><?php foreach ($mine as $pg) { View::partial('page-card', ['pg' => $pg, 'following' => in_array((int) $pg['id'], $following, true)]); } ?></div>
    <?php endif ?>
    <h2 class="zl-h2"><?= e(t('pages.discover')) ?></h2>
    <form class="zl-search" method="get" action="<?= e(url('pages')) ?>" role="search"><?= icon('search') ?><label class="sr-only" for="pq"><?= e(t('pages.search')) ?></label><input id="pq" class="zl-input" name="q" value="<?= e($q) ?>" placeholder="<?= e(t('pages.search')) ?>"><?php if ($cat): ?><input type="hidden" name="cat" value="<?= e($cat) ?>"><?php endif ?></form>
    <nav class="zl-chips" aria-label="<?= e(t('pages.category')) ?>">
        <a class="zl-chip<?= $cat === '' ? ' is-on' : '' ?>" href="<?= e(url('pages', $q !== '' ? ['q' => $q] : [])) ?>"><?= e(t('admin.all')) ?></a>
        <?php foreach (Pages::CATEGORIES as $c): ?><a class="zl-chip<?= $cat === $c ? ' is-on' : '' ?>" href="<?= e(url('pages', array_filter(['q' => $q, 'cat' => $c]))) ?>"><?= e(t('pages.cat_' . $c)) ?></a><?php endforeach ?>
    </nav>
    <?php if (!$all): ?><div class="zl-card zl-empty"><?= icon('page') ?><b><?= e(t('pages.none')) ?></b><span><?= e(t('pages.none_hint')) ?></span></div><?php else: ?>
    <div class="zl-groups-grid zl-grid3"><?php foreach ($all as $pg) { View::partial('page-card', ['pg' => $pg, 'following' => in_array((int) $pg['id'], $following, true)]); } ?></div>
    <?php endif ?>
</div>
<?php if (Auth::check()): ?>
<div class="zl-modal" id="page-modal" hidden role="dialog" aria-modal="true" aria-labelledby="page-title">
    <form class="zl-sheet" method="post" action="<?= e(url('pages')) ?>">
        <?= csrf_field() ?>
        <div class="zl-sheet-head"><b id="page-title"><?= e(t('pages.create')) ?></b><button type="button" class="zl-icon-btn" data-close aria-label="<?= e(t('common.close')) ?>"><?= icon('x') ?></button></div>
        <div class="zl-sheet-body">
            <p class="zl-muted"><?= e(t('pages.create_hint')) ?></p>
            <label class="zl-field"><span><?= e(t('pages.name')) ?></span><input class="zl-input" name="name" maxlength="80" minlength="2" required></label>
            <label class="zl-field"><span><?= e(t('pages.category')) ?></span><select class="zl-input" name="category"><?php foreach (Pages::CATEGORIES as $c): ?><option value="<?= $c ?>"><?= e(t('pages.cat_' . $c)) ?></option><?php endforeach ?></select></label>
            <label class="zl-field"><span><?= e(t('pages.about')) ?></span><textarea class="zl-input" name="description" maxlength="1000"></textarea></label>
            <button class="zl-btn zl-btn-main zl-btn-block"><?= e(t('pages.create')) ?></button>
        </div>
    </form>
</div>
<?php endif ?>
