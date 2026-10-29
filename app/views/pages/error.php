<?php $guest = !Auth::check(); ?>
<div class="<?= $guest ? 'zl-auth' : 'zl-col' ?>">
    <div class="zl-card zl-empty">
        <?= icon($code === 404 ? 'compass' : ($code === 403 ? 'lock' : 'flag')) ?>
        <b style="font-size:40px;line-height:1.1"><?= (int) $code ?></b>
        <span><?= e($message) ?></span>
        <div style="margin-top:18px"><a class="zl-btn zl-btn-main" href="<?= e(url('')) ?>"><?= e(t('nav.home')) ?></a></div>
    </div>
</div>
