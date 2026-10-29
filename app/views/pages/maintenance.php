<div class="zl-auth" style="max-width:560px">
    <section class="zl-card zl-center zl-maint">
        <div class="zl-done-ico"><?= icon('settings', 'zl-i zl-i-lg') ?></div>
        <h1 class="zl-h1"><?= e(t('maint.title')) ?></h1>
        <p class="zl-muted" style="margin-top:8px"><?= nl2br(e((string) setting('maintenance_text', '') ?: t('maint.text', ['site' => site_name()])), false) ?></p>
        <?php if (!Auth::check()): ?><a class="zl-btn zl-btn-soft zl-btn-sm" style="margin-top:18px" href="<?= e(url('login')) ?>"><?= e(t('maint.admin_login')) ?></a><?php endif ?>
    </section>
</div>
