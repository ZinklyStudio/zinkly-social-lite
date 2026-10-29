<div class="zl-auth">
    <div class="zl-card">
        <h1 class="zl-h1 zl-center"><?= e(t('auth.welcome_back')) ?></h1>
        <p class="zl-muted zl-center" style="margin:6px 0 18px"><?= e(t('auth.login_intro', ['site' => site_name()])) ?></p>
        <form method="post" action="<?= e(url('login')) ?>" class="zl-stack">
            <?= csrf_field() ?>
            <label class="zl-field"><span><?= e(t('auth.username_or_email')) ?></span><input class="zl-input" name="login" value="<?= e(old('login')) ?>" required autocomplete="username" autofocus></label>
            <label class="zl-field"><span><?= e(t('auth.password')) ?></span><input class="zl-input" type="password" name="password" required autocomplete="current-password"></label>
            <div class="zl-between">
                <label class="zl-row" style="font-size:14px"><input type="checkbox" name="remember" value="1"> <?= e(t('auth.remember')) ?></label>
                <a class="zl-faint" href="<?= e(url('forgot')) ?>"><?= e(t('auth.forgot')) ?></a>
            </div>
            <button class="zl-btn zl-btn-main zl-btn-lg"><?= e(t('auth.login')) ?></button>
        </form>
    </div>
    <?php if (setting('registration_open', '1') === '1'): ?><p class="zl-auth-foot"><?= e(t('auth.no_account')) ?> <a href="<?= e(url('register')) ?>"><?= e(t('auth.register')) ?></a></p><?php endif ?>
</div>
<?php unset($_SESSION['_old']); ?>
