<?php
/** "Go Pro" card in the right column. $u = signed-in member or null */
if (!$u || !Pro::enabled() || setting('pro_sidebar', '1') !== '1') {
    return;
}
$cheap = Pro::cheapest();
if (!$cheap && !Pro::isPro($u)) {
    return;
}
?>
<section class="zl-card zl-side-card zl-pro-card">
    <?php if (Pro::isPro($u)): ?>
        <div class="zl-pro-card-head"><span class="zl-pro-ico"><?= icon('crown', 'zl-i-sm') ?></span><h3><?= e(t('pro.you_are')) ?></h3></div>
        <p><?= e(t('pro.until', ['date' => Lang::date((int) $u['pro_until'])])) ?></p>
        <a class="zl-btn zl-btn-soft zl-btn-sm zl-btn-block" href="<?= e(url('pro')) ?>"><?= e(t('pro.manage')) ?></a>
    <?php else: ?>
        <div class="zl-pro-card-head"><span class="zl-pro-ico"><?= icon('crown', 'zl-i-sm') ?></span><h3><?= e((string) setting('pro_title', '') ?: t('pro.go')) ?></h3></div>
        <p><?= e((string) setting('pro_text', '') ?: t('pro.pitch')) ?></p>
        <a class="zl-btn zl-btn-main zl-btn-sm zl-btn-block" href="<?= e(url('pro')) ?>"><?= icon('sparkle', 'zl-i-sm') ?><?= e(t('pro.from', ['price' => Pro::money((int) $cheap['price_cents'], $cheap['currency']), 'period' => Pro::period((int) $cheap['period_days'])])) ?></a>
    <?php endif ?>
</section>
