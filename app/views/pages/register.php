<div class="zl-auth">
    <div class="zl-card">
        <h1 class="zl-h1 zl-center"><?= e(t('auth.create_account')) ?></h1>
        <p class="zl-muted zl-center" style="margin:6px 0 18px"><?= e(t('auth.register_intro', ['site' => site_name()])) ?></p>
        <form method="post" action="<?= e(url('register')) ?>" class="zl-stack">
            <?= csrf_field() ?>
            <label class="zl-field"><span><?= e(t('settings.name')) ?></span><input class="zl-input" name="name" value="<?= e(old('name')) ?>" maxlength="80" autocomplete="name"></label>
            <label class="zl-field"><span><?= e(t('auth.username')) ?></span><input class="zl-input" name="username" value="<?= e(old('username')) ?>" pattern="[A-Za-z0-9_]{3,32}" required autocomplete="username"><small class="zl-hint"><?= e(t('auth.username_rules')) ?></small></label>
            <label class="zl-field"><span><?= e(t('auth.email')) ?></span><input class="zl-input" type="email" name="email" value="<?= e(old('email')) ?>" required autocomplete="email"></label>
            <label class="zl-field"><span><?= e(t('auth.password')) ?></span><input class="zl-input" type="password" name="password" minlength="8" required autocomplete="new-password"><small class="zl-hint"><?= e(t('auth.password_hint')) ?></small></label>
            <label class="zl-check"><input type="checkbox" name="agree" value="1" required><span><?= t('auth.agree_html', ['terms' => '<a class="zl-link" href="' . e(url('terms')) . '" target="_blank">' . e(t('page.terms')) . '</a>', 'privacy' => '<a class="zl-link" href="' . e(url('privacy')) . '" target="_blank">' . e(t('page.privacy')) . '</a>']) ?></span></label>
            <button class="zl-btn zl-btn-main zl-btn-lg"><?= e(t('auth.register')) ?></button>
        </form>
    </div>
    <p class="zl-auth-foot"><?= e(t('auth.have_account')) ?> <a href="<?= e(url('login')) ?>"><?= e(t('auth.login')) ?></a></p>
</div>
<?php unset($_SESSION['_old']); ?>
