<div class="zl-col zl-col-wide">
    <div class="zl-card zl-pad zl-between zl-hero-card">
        <div><h1 class="zl-h1"><?= e(t('nav.groups')) ?></h1><p class="zl-faint"><?= e(t('group.intro')) ?></p></div>
        <button type="button" class="zl-btn zl-btn-main zl-btn-sm" data-open="group-modal"><?= icon('plus', 'zl-i-sm') ?><?= e(t('group.create')) ?></button>
    </div>
    <?php if ($mine): ?>
        <h2 class="zl-h2"><?= e(t('group.yours')) ?></h2>
        <div class="zl-groups-grid"><?php foreach ($mine as $g) { View::partial('group-card', ['g' => $g]); } ?></div>
    <?php endif ?>
    <div class="zl-between"><h2 class="zl-h2"><?= e(t('group.discover')) ?></h2></div>
    <form class="zl-search" method="get" action="<?= e(url('groups')) ?>" role="search"><?= icon('search') ?><label class="sr-only" for="gq"><?= e(t('group.search')) ?></label><input id="gq" class="zl-input" name="q" value="<?= e($q) ?>" placeholder="<?= e(t('group.search')) ?>"><?php if ($cat): ?><input type="hidden" name="cat" value="<?= e($cat) ?>"><?php endif ?></form>
    <nav class="zl-chips" aria-label="<?= e(t('group.category')) ?>">
        <a class="zl-chip<?= $cat === '' ? ' is-on' : '' ?>" href="<?= e(url('groups', $q !== '' ? ['q' => $q] : [])) ?>"><?= e(t('admin.all')) ?></a>
        <?php foreach (Groups::CATEGORIES as $c): ?><a class="zl-chip<?= $cat === $c ? ' is-on' : '' ?>" href="<?= e(url('groups', array_filter(['q' => $q, 'cat' => $c]))) ?>"><?= e(t('group.cat_' . $c)) ?></a><?php endforeach ?>
    </nav>
    <?php if (!$all): ?><div class="zl-card zl-empty"><?= icon('users') ?><b><?= e(t('group.none')) ?></b></div><?php else: ?>
    <div class="zl-groups-grid"><?php foreach ($all as $g) { View::partial('group-card', ['g' => $g]); } ?></div>
    <?php endif ?>
</div>
<div class="zl-modal" id="group-modal" hidden role="dialog" aria-modal="true" aria-labelledby="group-title">
    <form class="zl-sheet" method="post" action="<?= e(url('groups')) ?>">
        <?= csrf_field() ?>
        <div class="zl-sheet-head"><b id="group-title"><?= e(t('group.create')) ?></b><button type="button" class="zl-icon-btn" data-close aria-label="<?= e(t('common.close')) ?>"><?= icon('x') ?></button></div>
        <div class="zl-sheet-body">
            <label class="zl-field"><span><?= e(t('group.name')) ?></span><input class="zl-input" name="name" maxlength="80" minlength="3" required></label>
            <label class="zl-field"><span><?= e(t('group.category')) ?></span><select class="zl-input" name="category"><option value=""><?= e(t('group.cat_none')) ?></option><?php foreach (Groups::CATEGORIES as $c): ?><option value="<?= $c ?>"><?= e(t('group.cat_' . $c)) ?></option><?php endforeach ?></select></label>
            <label class="zl-field"><span><?= e(t('group.description')) ?></span><textarea class="zl-input" name="description" maxlength="500"></textarea></label>
            <label class="zl-check"><input type="radio" name="privacy" value="public" checked><span><?= e(t('group.public')) ?><small><?= e(t('group.public_hint')) ?></small></span></label>
            <label class="zl-check"><input type="radio" name="privacy" value="private"><span><?= e(t('group.private')) ?><small><?= e(t('group.private_hint')) ?></small></span></label>
            <button class="zl-btn zl-btn-main zl-btn-block"><?= e(t('group.create')) ?></button>
        </div>
    </form>
</div>
