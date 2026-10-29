<?php
/** Go Pro: plans and payment. $plans, $gateways, $pending, $isPro, $proUntil */
$custom = trim((string) setting('custom_instructions', ''));
?>
<div class="zl-col zl-stack">
    <section class="zl-card zl-pad zl-pro-hero">
        <span class="zl-pro-ico zl-pro-ico-lg"><?= icon('crown') ?></span>
        <h1 class="zl-h1"><?= e((string) setting('pro_title', '') ?: t('pro.go')) ?></h1>
        <p class="zl-muted"><?= e((string) setting('pro_text', '') ?: t('pro.pitch')) ?></p>
        <?php if ($isPro): ?><p class="zl-alert zl-alert-ok" role="status"><?= icon('check', 'zl-i-sm') ?> <?= e(t('pro.you_are')) ?> · <?= e(t('pro.until', ['date' => Lang::date($proUntil)])) ?><br><small><?= e(t('pro.extend_hint')) ?></small></p><?php endif ?>
        <ul class="zl-pro-perks">
            <?php if (setting('pro_badge', '1') === '1'): ?><li><?= icon('check', 'zl-i-sm') ?><?= e(t('pro.perk_badge')) ?> <span class="zl-pro-badge">PRO</span></li><?php endif ?>
            <?php if ((int) setting('pro_upload_mb', '0') > (int) setting('max_upload_mb', '8')): ?><li><?= icon('check', 'zl-i-sm') ?><?= e(t('pro.perk_upload', ['mb' => (int) setting('pro_upload_mb')])) ?></li><?php endif ?>
            <li><?= icon('check', 'zl-i-sm') ?><?= e(t('pro.perk_support')) ?></li>
        </ul>
    </section>

    <?php foreach ($pending as $pp): ?>
        <div class="zl-alert zl-alert-warn" role="status"><?= icon('clock', 'zl-i-sm') ?> <?= e(t('pro.pending', ['amount' => Pro::money((int) $pp['amount_cents'], $pp['currency']), 'date' => Lang::date((int) $pp['created_at'])])) ?></div>
    <?php endforeach ?>

    <?php if (!$plans): ?>
        <div class="zl-card zl-pad"><div class="zl-empty"><?= icon('crown') ?><b><?= e(t('pro.no_plans')) ?></b></div></div>
    <?php elseif (!$gateways): ?>
        <div class="zl-card zl-pad"><div class="zl-empty"><?= icon('card') ?><b><?= e(t('pro.no_gateways')) ?></b></div></div>
    <?php else: ?>
    <form class="zl-stack" method="post" action="<?= e(url('pro/checkout')) ?>" data-pro-form>
        <?= csrf_field() ?>
        <div class="zl-pro-plans" role="radiogroup" aria-label="<?= e(t('pro.choose_plan')) ?>">
            <?php foreach ($plans as $i => $pl): ?>
                <input type="radio" class="sr-only" name="plan_id" value="<?= (int) $pl['id'] ?>" id="plan-<?= (int) $pl['id'] ?>" <?= $i === 0 ? 'checked' : '' ?>>
                <label class="zl-card zl-pro-plan" for="plan-<?= (int) $pl['id'] ?>">
                    <b><?= e($pl['name']) ?></b>
                    <span class="zl-pro-price"><?= e(Pro::money((int) $pl['price_cents'], $pl['currency'])) ?><small> / <?= e(Pro::period((int) $pl['period_days'])) ?></small></span>
                    <?php if (trim((string) $pl['perks']) !== ''): ?><ul><?php foreach (preg_split('/\R/', (string) $pl['perks']) as $perk): if (trim($perk) === '') continue; ?><li><?= icon('check', 'zl-i-xs') ?><?= e(trim($perk, " -•\t")) ?></li><?php endforeach ?></ul><?php endif ?>
                </label>
            <?php endforeach ?>
        </div>
        <section class="zl-card zl-pad zl-stack">
            <h2 class="zl-h2"><?= icon('card', 'zl-i-sm') ?> <?= e(t('pro.pay_with')) ?></h2>
            <div class="zl-seg<?= count($gateways) > 2 ? ' zl-seg3' : '' ?>" role="radiogroup" aria-label="<?= e(t('pro.pay_with')) ?>">
                <?php foreach ($gateways as $i => $g): ?>
                    <input type="radio" class="sr-only" name="gateway" value="<?= e($g) ?>" id="gw-<?= e($g) ?>" <?= $i === 0 ? 'checked' : '' ?>>
                    <label for="gw-<?= e($g) ?>"><?= e(Pro::gatewayName($g)) ?><small><?= e(t('pro.gw_' . $g . '_hint')) ?></small></label>
                <?php endforeach ?>
                <?php if (in_array('custom', $gateways, true)): ?>
                <div class="zl-seg-custom zl-stack">
                    <?php if ($custom !== ''): ?><div class="zl-note zl-pre"><?= e($custom) ?></div><?php endif ?>
                    <label class="zl-field"><span><?= e(t('pro.custom_note')) ?></span><textarea class="zl-input" name="note" rows="2" maxlength="500" placeholder="<?= e(t('pro.custom_note_ph')) ?>"></textarea></label>
                </div>
                <?php endif ?>
            </div>
            <button class="zl-btn zl-btn-main zl-btn-block"><?= icon('crown', 'zl-i-sm') ?><?= e($isPro ? t('pro.extend') : t('pro.continue')) ?></button>
            <p class="zl-hint zl-center"><?= e(t('pro.secure_hint')) ?></p>
        </section>
    </form>
    <?php endif ?>
</div>
