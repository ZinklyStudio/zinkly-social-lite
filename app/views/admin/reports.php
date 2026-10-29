<div class="zl-settings zl-admin">
    <?php View::partial('admin-nav', ['anav' => $anav]); ?>
    <div class="zl-stack">
        <div class="zl-card zl-pad"><h1 class="zl-h1"><?= e(t('admin.reports')) ?></h1><p class="zl-faint"><?= e(t('admin.reports_hint')) ?></p></div>
        <?php if (!$reports): ?><div class="zl-card zl-empty"><?= icon('check') ?><b><?= e(t('admin.no_reports')) ?></b></div><?php endif ?>
        <?php foreach ($reports as $r): ?>
            <div class="zl-card zl-pad">
                <div class="zl-between" style="flex-wrap:wrap"><span class="zl-pill is-banned"><?= e(t('report.r_' . $r['reason'])) ?></span><small class="zl-faint"><?= e(t('admin.report_' . $r['target_type'])) ?> · <?= e(ago((int) $r['created_at'])) ?><?php if ($r['reporter']): ?> · @<?= e($r['reporter']['username']) ?><?php endif ?></small></div>
                <?php if ($r['preview']): ?><p class="zl-note"><a href="<?= e($r['preview']['url']) ?>"><?= e($r['preview']['text'] ?: '—') ?></a></p><?php else: ?><p class="zl-note"><?= e(t('admin.already_removed')) ?></p><?php endif ?>
                <?php if ($r['details'] !== ''): ?><p class="zl-muted" style="font-size:14px">“<?= e($r['details']) ?>”</p><?php endif ?>
                <div class="zl-row" style="margin-top:12px;justify-content:flex-end">
                    <form method="post" action="<?= e(url('admin/reports/' . $r['id'] . '/dismiss')) ?>"><?= csrf_field() ?><button class="zl-btn zl-btn-soft zl-btn-sm"><?= e(t('admin.dismiss')) ?></button></form>
                    <?php if ($r['preview'] && $r['target_type'] === 'post'): ?><form method="post" action="<?= e(url('admin/reports/' . $r['id'] . '/hide')) ?>"><?= csrf_field() ?><button class="zl-btn zl-btn-soft zl-btn-sm"><?= icon('eye-off', 'zl-i-sm') ?><?= e(t('post.hide')) ?></button></form><?php endif ?>
                    <?php if ($r['preview']): ?><form method="post" action="<?= e(url('admin/reports/' . $r['id'] . '/remove')) ?>" data-confirm="<?= e(t('admin.remove_confirm')) ?>"><?= csrf_field() ?><button class="zl-btn zl-btn-danger zl-btn-sm"><?= e($r['target_type'] === 'user' ? t('admin.ban') : t('admin.remove')) ?></button></form><?php endif ?>
                </div>
            </div>
        <?php endforeach ?>
    </div>
</div>
