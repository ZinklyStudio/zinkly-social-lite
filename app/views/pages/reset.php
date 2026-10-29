<div class="zl-auth">
    <div class="zl-card">
        <h1 class="zl-h1 zl-center"><?= e(t('auth.new_password')) ?></h1>
        <form method="post" action="<?= e(url('reset/' . $token)) ?>" class="zl-stack" style="margin-top:18px">
            <?= csrf_field() ?>
            <label class="zl-field"><span><?= e(t('auth.password')) ?></span><input class="zl-input" type="password" name="password" minlength="8" required autocomplete="new-password" autofocus><small class="zl-hint"><?= e(t('auth.password_hint')) ?></small></label>
            <button class="zl-btn zl-btn-main zl-btn-lg"><?= e(t('auth.save_password')) ?></button>
        </form>
    </div>
</div>
