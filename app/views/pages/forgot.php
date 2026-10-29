<div class="zl-auth">
    <div class="zl-card">
        <h1 class="zl-h1 zl-center"><?= e(t('auth.forgot')) ?></h1>
        <p class="zl-muted zl-center" style="margin:6px 0 18px"><?= e(t('auth.forgot_intro')) ?></p>
        <form method="post" action="<?= e(url('forgot')) ?>" class="zl-stack">
            <?= csrf_field() ?>
            <label class="zl-field"><span><?= e(t('auth.email')) ?></span><input class="zl-input" type="email" name="email" required autocomplete="email" autofocus></label>
            <button class="zl-btn zl-btn-main zl-btn-lg"><?= e(t('auth.send_link')) ?></button>
        </form>
    </div>
    <p class="zl-auth-foot"><a href="<?= e(url('login')) ?>"><?= e(t('auth.back_login')) ?></a></p>
</div>
