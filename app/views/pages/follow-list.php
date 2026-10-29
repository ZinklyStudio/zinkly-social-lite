<div class="zl-col">
    <div class="zl-card zl-pad zl-row">
        <a class="zl-icon-btn" href="<?= e(url('u/' . $u['username'])) ?>" aria-label="<?= e(t('common.back')) ?>"><?= icon('arrow-left') ?></a>
        <div><h1 class="zl-h1"><?= e(t('profile.' . $list)) ?></h1><p class="zl-faint"><?= e(display_name($u)) ?> · @<?= e($u['username']) ?></p></div>
    </div>
    <?php if (!$people): ?>
        <div class="zl-card zl-empty"><?= icon('users') ?><b><?= e(t('profile.no_' . $list)) ?></b></div>
    <?php else: ?>
        <div class="zl-card zl-people"><?php foreach ($people as $pp) { View::partial('person', ['p' => $pp]); } ?></div>
    <?php endif ?>
</div>
