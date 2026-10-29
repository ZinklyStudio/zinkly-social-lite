<?php
/** Admin › Pro: switch Pro on, payment methods, plans, payments and Pro members. */
$sw = function (string $k, string $def = '0', string $label = '', string $hint = '') {
    return '<label class="zl-check"><span>' . e($label) . '<small>' . e($hint) . '</small></span><span class="zl-switch"><input type="checkbox" name="' . e($k) . '" value="1" '
        . (setting($k, $def) === '1' ? 'checked' : '') . '><span></span></span></label>';
};
$plan = $editPlan ?? ['id' => 0, 'name' => '', 'price_cents' => 0, 'currency' => 'EUR', 'period_days' => 30, 'perks' => '', 'active' => 1, 'position' => count($plans)];
$statusPill = ['paid' => ' is-ok', 'pending' => ' is-warn', 'failed' => ' is-bad', 'rejected' => ' is-bad', 'canceled' => ''];
?>
<div class="zl-settings zl-admin">
    <?php View::partial('admin-nav', ['anav' => $anav]); ?>
    <div class="zl-stack">
        <div class="zl-card zl-pad zl-between" style="flex-wrap:wrap">
            <div><h1 class="zl-h1"><?= icon('crown', 'zl-i-sm') ?> <?= e(t('admin.pro')) ?></h1><p class="zl-faint"><?= e(t('admin.pro_hint')) ?></p></div>
            <span class="zl-pill<?= Pro::enabled() ? ' is-ok' : '' ?>"><?= e(Pro::enabled() ? t('pro.on') : t('pro.off')) ?></span>
        </div>
        <div class="zl-stat-row">
            <div class="zl-card zl-pad"><small class="zl-faint"><?= e(t('pro.stat_members')) ?></small><b class="zl-h1"><?= (int) $stats['members'] ?></b></div>
            <div class="zl-card zl-pad"><small class="zl-faint"><?= e(t('pro.stat_pending')) ?></small><b class="zl-h1"><?= (int) $stats['pending'] ?></b></div>
            <div class="zl-card zl-pad"><small class="zl-faint"><?= e(t('pro.stat_30d')) ?></small><b class="zl-h1"><?= $stats['paid30'] ? e(implode(' + ', array_map(fn ($r) => Pro::money((int) $r['total'], $r['currency']), $stats['paid30']))) : '0' ?></b></div>
        </div>

        <form class="zl-admin-grid" method="post" action="<?= e(url('admin/pro/settings')) ?>" autocomplete="off">
            <?= csrf_field() ?>
            <section class="zl-card zl-pad zl-stack">
                <h2 class="zl-h2"><?= icon('settings', 'zl-i-sm') ?> <?= e(t('pro.sec_general')) ?></h2>
                <?= $sw('pro_enabled', '0', t('pro.f_enabled'), t('pro.f_enabled_hint')) ?>
                <?= $sw('pro_sidebar', '1', t('pro.f_sidebar'), t('pro.f_sidebar_hint')) ?>
                <?= $sw('pro_badge', '1', t('pro.f_badge'), t('pro.f_badge_hint')) ?>
                <label class="zl-field"><span><?= e(t('pro.f_title')) ?></span><input class="zl-input" name="pro_title" maxlength="80" value="<?= e((string) setting('pro_title', '')) ?>" placeholder="<?= e(t('pro.go')) ?>"></label>
                <label class="zl-field"><span><?= e(t('pro.f_text')) ?></span><textarea class="zl-input" name="pro_text" maxlength="300" rows="2" placeholder="<?= e(t('pro.pitch')) ?>"><?= e((string) setting('pro_text', '')) ?></textarea></label>
                <label class="zl-field"><span><?= e(t('pro.f_upload')) ?></span><input class="zl-input" type="number" name="pro_upload_mb" min="0" max="200" value="<?= (int) setting('pro_upload_mb', '0') ?>"><small class="zl-hint"><?= e(t('pro.f_upload_hint', ['mb' => (int) setting('max_upload_mb', '8')])) ?></small></label>
            </section>
            <section class="zl-card zl-pad zl-stack">
                <h2 class="zl-h2"><?= icon('card', 'zl-i-sm') ?> <?= e(t('pro.sec_gateways')) ?></h2>
                <?= $sw('pay_stripe', '0', t('pro.gw_stripe'), t('pro.gw_stripe_admin')) ?>
                <label class="zl-field"><span><?= e(t('pro.stripe_secret')) ?></span><input class="zl-input" type="password" name="stripe_secret" placeholder="<?= setting('stripe_secret', '') !== '' ? e(t('pro.key_saved')) : 'sk_live_… / sk_test_…' ?>" autocomplete="new-password" spellcheck="false"></label>
                <?php if (setting('stripe_secret', '') !== ''): ?><label class="zl-row" style="font-size:13px"><input type="checkbox" name="stripe_secret_clear" value="1"> <?= e(t('pro.key_clear')) ?></label><?php endif ?>
                <hr class="zl-hr">
                <?= $sw('pay_paypal', '0', t('pro.gw_paypal'), t('pro.gw_paypal_admin')) ?>
                <div class="zl-grid2">
                    <label class="zl-field"><span><?= e(t('pro.paypal_client')) ?></span><input class="zl-input" name="paypal_client" value="<?= e((string) setting('paypal_client', '')) ?>" spellcheck="false"></label>
                    <label class="zl-field"><span><?= e(t('pro.paypal_secret')) ?></span><input class="zl-input" type="password" name="paypal_secret" placeholder="<?= setting('paypal_secret', '') !== '' ? e(t('pro.key_saved')) : '' ?>" autocomplete="new-password" spellcheck="false"></label>
                </div>
                <?php if (setting('paypal_secret', '') !== ''): ?><label class="zl-row" style="font-size:13px"><input type="checkbox" name="paypal_secret_clear" value="1"> <?= e(t('pro.key_clear')) ?></label><?php endif ?>
                <label class="zl-field"><span><?= e(t('pro.paypal_mode')) ?></span><select class="zl-input" name="paypal_mode"><option value="sandbox" <?= setting('paypal_mode', 'sandbox') === 'sandbox' ? 'selected' : '' ?>><?= e(t('pro.mode_sandbox')) ?></option><option value="live" <?= setting('paypal_mode', 'sandbox') === 'live' ? 'selected' : '' ?>><?= e(t('pro.mode_live')) ?></option></select></label>
                <hr class="zl-hr">
                <?= $sw('pay_custom', '0', t('pro.gw_custom'), t('pro.gw_custom_admin')) ?>
                <label class="zl-field"><span><?= e(t('pro.custom_title')) ?></span><input class="zl-input" name="custom_title" maxlength="80" value="<?= e((string) setting('custom_title', '')) ?>" placeholder="<?= e(t('pro.custom_title_ph')) ?>"></label>
                <label class="zl-field"><span><?= e(t('pro.custom_instructions')) ?></span><textarea class="zl-input" name="custom_instructions" maxlength="3000" rows="4" placeholder="<?= e(t('pro.custom_instructions_ph')) ?>"><?= e((string) setting('custom_instructions', '')) ?></textarea></label>
            </section>
            <div class="zl-span-all"><button class="zl-btn zl-btn-main"><?= icon('check', 'zl-i-sm') ?><?= e(t('common.save')) ?></button></div>
        </form>

        <div class="zl-admin-split" id="plans">
            <div class="zl-card zl-table-wrap"><table class="zl-table">
                <thead><tr><th><?= e(t('pro.plan')) ?></th><th><?= e(t('pro.price')) ?></th><th><?= e(t('admin.status')) ?></th><th><span class="sr-only"><?= e(t('admin.actions')) ?></span></th></tr></thead>
                <tbody><?php foreach ($plans as $pl): ?><tr class="<?= (int) $plan['id'] === (int) $pl['id'] ? 'is-selected' : '' ?>">
                    <td><a class="zl-strong" href="<?= e(url('admin/pro', ['plan' => $pl['id']])) ?>#plans"><?= e($pl['name']) ?></a></td>
                    <td><?= e(Pro::money((int) $pl['price_cents'], $pl['currency'])) ?> / <?= e(Pro::period((int) $pl['period_days'])) ?></td>
                    <td><span class="zl-pill<?= $pl['active'] ? ' is-ok' : '' ?>"><?= e($pl['active'] ? t('pro.active') : t('pro.inactive')) ?></span></td>
                    <td><div class="zl-row" style="gap:4px;justify-content:flex-end"><a class="zl-icon-btn" href="<?= e(url('admin/pro', ['plan' => $pl['id']])) ?>#plans" aria-label="<?= e(t('post.edit')) ?>"><?= icon('edit') ?></a>
                        <form method="post" action="<?= e(url('admin/pro/plans/' . (int) $pl['id'] . '/delete')) ?>" data-confirm="<?= e(t('pro.delete_plan')) ?>"><?= csrf_field() ?><button class="zl-icon-btn" aria-label="<?= e(t('post.delete')) ?>"><?= icon('trash') ?></button></form></div></td>
                </tr><?php endforeach ?>
                <?php if (!$plans): ?><tr><td colspan="4"><div class="zl-empty"><?= icon('crown') ?><b><?= e(t('pro.no_plans_admin')) ?></b></div></td></tr><?php endif ?></tbody>
            </table></div>
            <div class="zl-card zl-pad">
                <form class="zl-stack" method="post" action="<?= e(url('admin/pro/plans')) ?>">
                    <?= csrf_field() ?><input type="hidden" name="id" value="<?= (int) $plan['id'] ?>">
                    <h2 class="zl-h2"><?= e($plan['id'] ? t('pro.edit_plan') : t('pro.new_plan')) ?></h2>
                    <label class="zl-field"><span><?= e(t('pro.plan_name')) ?></span><input class="zl-input" name="name" maxlength="80" value="<?= e($plan['name']) ?>" placeholder="<?= e(t('pro.plan_name_ph')) ?>" required></label>
                    <div class="zl-grid2">
                        <label class="zl-field"><span><?= e(t('pro.price')) ?></span><input class="zl-input" name="price" inputmode="decimal" value="<?= $plan['price_cents'] ? e(Pro::amount((int) $plan['price_cents'], $plan['currency'])) : '' ?>" placeholder="4.99" required></label>
                        <label class="zl-field"><span><?= e(t('pro.currency')) ?></span><input class="zl-input" name="currency" maxlength="3" value="<?= e($plan['currency']) ?>" spellcheck="false" style="text-transform:uppercase"></label>
                    </div>
                    <label class="zl-field"><span><?= e(t('pro.period')) ?></span><select class="zl-input" name="period_days">
                        <?php foreach ([7, 30, 90, 365] as $d): ?><option value="<?= $d ?>" <?= (int) $plan['period_days'] === $d ? 'selected' : '' ?>><?= e(Pro::period($d)) ?></option><?php endforeach ?>
                        <?php if (!in_array((int) $plan['period_days'], [7, 30, 90, 365], true)): ?><option value="<?= (int) $plan['period_days'] ?>" selected><?= e(Pro::period((int) $plan['period_days'])) ?></option><?php endif ?>
                    </select></label>
                    <label class="zl-field"><span><?= e(t('pro.perks')) ?></span><textarea class="zl-input" name="perks" rows="4" maxlength="1000" placeholder="<?= e(t('pro.perks_ph')) ?>"><?= e((string) $plan['perks']) ?></textarea><small class="zl-hint"><?= e(t('pro.perks_hint')) ?></small></label>
                    <div class="zl-grid2">
                        <label class="zl-check"><span><?= e(t('pro.active')) ?></span><span class="zl-switch"><input type="checkbox" name="active" value="1" <?= $plan['active'] ? 'checked' : '' ?>><span></span></span></label>
                        <label class="zl-field"><span><?= e(t('sitepages.position')) ?></span><input class="zl-input" type="number" name="position" min="0" max="999" value="<?= (int) $plan['position'] ?>"></label>
                    </div>
                    <div class="zl-row"><button class="zl-btn zl-btn-main"><?= icon('check', 'zl-i-sm') ?><?= e(t('common.save')) ?></button><?php if ($plan['id']): ?><a class="zl-btn zl-btn-soft" href="<?= e(url('admin/pro')) ?>#plans"><?= e(t('pro.new_plan')) ?></a><?php endif ?></div>
                </form>
            </div>
        </div>

        <section class="zl-card" id="payments">
            <div class="zl-pad zl-between" style="flex-wrap:wrap;gap:10px"><h2 class="zl-h2"><?= icon('card', 'zl-i-sm') ?> <?= e(t('pro.payments')) ?></h2>
                <nav class="zl-chips"><?php foreach (['' => t('pro.all'), 'pending' => t('pro.s_pending'), 'paid' => t('pro.s_paid'), 'failed' => t('pro.s_failed')] as $k => $l): ?><a class="zl-chip<?= $filter === $k ? ' is-on' : '' ?>" href="<?= e(url('admin/pro', $k ? ['status' => $k] : [])) ?>#payments"><?= e($l) ?></a><?php endforeach ?></nav></div>
            <div class="zl-table-wrap"><table class="zl-table">
                <thead><tr><th><?= e(t('admin.member')) ?></th><th><?= e(t('pro.amount')) ?></th><th><?= e(t('pro.method')) ?></th><th><?= e(t('admin.status')) ?></th><th><span class="sr-only"><?= e(t('admin.actions')) ?></span></th></tr></thead>
                <tbody><?php foreach ($payments as $p): $pu = $payUsers[(int) $p['user_id']] ?? null; ?><tr>
                    <td><?php if ($pu): ?><a class="zl-strong" href="<?= e(url('u/' . $pu['username'])) ?>">@<?= e($pu['username']) ?></a><?php else: ?>—<?php endif ?><br><small class="zl-faint">#<?= (int) $p['id'] ?> · <?= e(Lang::date((int) $p['created_at'])) ?></small></td>
                    <td><?= e(Pro::money((int) $p['amount_cents'], $p['currency'])) ?><br><small class="zl-faint"><?= e(tc('pro.n_days', (int) $p['days'])) ?></small></td>
                    <td><?= e(Pro::gatewayName($p['gateway'])) ?><?php if ($p['note'] !== ''): ?><br><small class="zl-faint"><?= e(str_limit($p['note'], 80)) ?></small><?php endif ?></td>
                    <td><span class="zl-pill<?= $statusPill[$p['status']] ?? '' ?>"><?= e(t('pro.s_' . $p['status'])) ?></span></td>
                    <td><div class="zl-row" style="gap:4px;justify-content:flex-end">
                        <?php if ($p['status'] === 'pending' && $p['gateway'] !== 'custom'): ?><form method="post" action="<?= e(url('admin/pro/payments/' . (int) $p['id'] . '/check')) ?>"><?= csrf_field() ?><button class="zl-btn zl-btn-soft zl-btn-sm"><?= e(t('pro.check')) ?></button></form><?php endif ?>
                        <?php if (in_array($p['status'], ['pending', 'rejected'], true)): ?><form method="post" action="<?= e(url('admin/pro/payments/' . (int) $p['id'] . '/approve')) ?>" data-confirm="<?= e(t('pro.approve_confirm')) ?>"><?= csrf_field() ?><button class="zl-btn zl-btn-main zl-btn-sm"><?= e(t('pro.approve')) ?></button></form><?php endif ?>
                        <?php if ($p['status'] === 'pending'): ?><form method="post" action="<?= e(url('admin/pro/payments/' . (int) $p['id'] . '/reject')) ?>"><?= csrf_field() ?><button class="zl-btn zl-btn-soft zl-btn-sm"><?= e(t('pro.reject')) ?></button></form><?php endif ?>
                    </div></td>
                </tr><?php endforeach ?>
                <?php if (!$payments): ?><tr><td colspan="5"><div class="zl-empty"><?= icon('card') ?><b><?= e(t('pro.no_payments')) ?></b></div></td></tr><?php endif ?></tbody>
            </table></div>
        </section>

        <section class="zl-card" id="members">
            <div class="zl-pad zl-stack">
                <h2 class="zl-h2"><?= icon('users', 'zl-i-sm') ?> <?= e(t('pro.members')) ?></h2>
                <form class="zl-row zl-pro-grant" method="post" action="<?= e(url('admin/pro/grant')) ?>" style="flex-wrap:wrap">
                    <?= csrf_field() ?>
                    <label class="sr-only" for="pg-user"><?= e(t('pro.username')) ?></label><input id="pg-user" class="zl-input" name="username" placeholder="@username" required style="flex:1;min-width:160px">
                    <label class="sr-only" for="pg-days"><?= e(t('pro.days')) ?></label><input id="pg-days" class="zl-input" type="number" name="days" min="1" max="3650" value="30" style="width:100px">
                    <button class="zl-btn zl-btn-main zl-btn-sm"><?= e(t('pro.give_days')) ?></button>
                    <button class="zl-btn zl-btn-soft zl-btn-sm" name="revoke" value="1"><?= e(t('pro.revoke')) ?></button>
                </form>
            </div>
            <div class="zl-table-wrap"><table class="zl-table">
                <thead><tr><th><?= e(t('admin.member')) ?></th><th><?= e(t('pro.until_col')) ?></th></tr></thead>
                <tbody><?php foreach ($members as $m): ?><tr><td><div class="zl-row"><?= avatar_html($m, 'sm') ?><a class="zl-strong" href="<?= e(url('u/' . $m['username'])) ?>"><?= e(display_name($m)) ?></a><?= Pro::badge($m) ?></div></td><td><?= e(Lang::date((int) $m['pro_until'])) ?></td></tr><?php endforeach ?>
                <?php if (!$members): ?><tr><td colspan="2"><div class="zl-empty"><?= icon('users') ?><b><?= e(t('pro.no_members')) ?></b></div></td></tr><?php endif ?></tbody>
            </table></div>
        </section>
    </div>
</div>
