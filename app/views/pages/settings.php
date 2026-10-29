<?php $tabs = ['profile' => 'user', 'account' => 'lock', 'privacy' => 'shield', 'display' => 'sun', 'blocked' => 'ban']; ?>
<div class="zl-settings">
    <nav class="zl-card zl-settings-nav" aria-label="<?= e(t('nav.settings')) ?>">
        <?php foreach ($tabs as $k => $ic): ?><a href="<?= e(url('settings', ['tab' => $k])) ?>" class="<?= $tab === $k ? 'is-on' : '' ?>" <?= $tab === $k ? 'aria-current="page"' : '' ?>><?= icon($ic, 'zl-i-sm') ?><?= e(t('settings.tab_' . $k)) ?></a><?php endforeach ?>
    </nav>
    <div class="zl-card zl-pad">
    <?php if ($tab === 'profile'): ?>
        <form method="post" action="<?= e(url('settings/profile')) ?>" enctype="multipart/form-data" class="zl-stack">
            <?= csrf_field() ?>
            <h1 class="zl-h1"><?= e(t('settings.tab_profile')) ?></h1>
            <div class="zl-row"><?= avatar_html($u, 'lg') ?>
                <label class="zl-field" style="flex:1"><span><?= e(t('settings.avatar')) ?></span><input class="zl-input" type="file" name="avatar" accept="image/jpeg,image/png,image/webp,image/gif"></label></div>
            <?php if ($u['avatar']): ?><label class="zl-row" style="font-size:14px"><input type="checkbox" name="remove_avatar" value="1"> <?= e(t('settings.remove_avatar')) ?></label><?php endif ?>
            <label class="zl-field"><span><?= e(t('settings.cover')) ?></span><input class="zl-input" type="file" name="cover" accept="image/jpeg,image/png,image/webp"></label>
            <?php if ($u['cover']): ?><label class="zl-row" style="font-size:14px"><input type="checkbox" name="remove_cover" value="1"> <?= e(t('settings.remove_cover')) ?></label><?php endif ?>
            <div class="zl-grid2">
                <label class="zl-field"><span><?= e(t('settings.name')) ?></span><input class="zl-input" name="name" value="<?= e($u['name']) ?>" maxlength="80"></label>
                <label class="zl-field"><span><?= e(t('auth.username')) ?></span><input class="zl-input" name="username" value="<?= e($u['username']) ?>" pattern="[A-Za-z0-9_]{3,32}" required></label>
                <label class="zl-field"><span><?= e(t('settings.location')) ?></span><input class="zl-input" name="location" value="<?= e($u['location']) ?>" maxlength="80"></label>
                <label class="zl-field"><span><?= e(t('settings.website')) ?></span><input class="zl-input" name="website" value="<?= e($u['website']) ?>" maxlength="190" inputmode="url"></label>
            </div>
            <label class="zl-field"><span><?= e(t('settings.bio')) ?></span><textarea class="zl-input" name="bio" maxlength="300"><?= e($u['bio']) ?></textarea></label>
            <button class="zl-btn zl-btn-main"><?= e(t('common.save')) ?></button>
        </form>
    <?php elseif ($tab === 'account'): ?>
        <form method="post" action="<?= e(url('settings/account')) ?>" class="zl-stack">
            <?= csrf_field() ?>
            <h1 class="zl-h1"><?= e(t('settings.tab_account')) ?></h1>
            <label class="zl-field"><span><?= e(t('auth.email')) ?></span><input class="zl-input" type="email" name="email" value="<?= e($u['email']) ?>" required autocomplete="email"></label>
            <label class="zl-field"><span><?= e(t('settings.new_password')) ?></span><input class="zl-input" type="password" name="new_password" minlength="8" autocomplete="new-password"><small class="zl-hint"><?= e(t('settings.new_password_hint')) ?></small></label>
            <label class="zl-field"><span><?= e(t('settings.current_password')) ?></span><input class="zl-input" type="password" name="current_password" required autocomplete="current-password"></label>
            <button class="zl-btn zl-btn-main"><?= e(t('common.save')) ?></button>
        </form>
        <hr style="border:0;border-top:1px solid var(--border);margin:24px 0">
        <form method="post" action="<?= e(url('settings/delete')) ?>" class="zl-stack" data-confirm="<?= e(t('settings.delete_confirm')) ?>">
            <?= csrf_field() ?>
            <h2 class="zl-h2"><?= e(t('settings.delete_account')) ?></h2>
            <p class="zl-muted"><?= e(t('settings.delete_hint')) ?></p>
            <label class="zl-field"><span><?= e(t('settings.current_password')) ?></span><input class="zl-input" type="password" name="current_password" required autocomplete="current-password"></label>
            <button class="zl-btn zl-btn-danger"><?= icon('trash', 'zl-i-sm') ?><?= e(t('settings.delete_account')) ?></button>
        </form>
    <?php elseif ($tab === 'privacy'): ?>
        <form method="post" action="<?= e(url('settings/privacy')) ?>" class="zl-stack">
            <?= csrf_field() ?>
            <h1 class="zl-h1"><?= e(t('settings.tab_privacy')) ?></h1>
            <label class="zl-check"><span><?= e(t('settings.private_account')) ?><small><?= e(t('settings.private_account_hint')) ?></small></span><span class="zl-switch"><input type="checkbox" name="private_account" value="1" <?= $u['private_account'] ? 'checked' : '' ?>><span></span></span></label>
            <label class="zl-field"><span><?= e(t('settings.who_can_message')) ?></span>
                <select class="zl-input" name="who_can_message"><?php foreach (['everyone', 'following', 'nobody'] as $o): ?><option value="<?= $o ?>" <?= $u['who_can_message'] === $o ? 'selected' : '' ?>><?= e(t('settings.msg_' . $o)) ?></option><?php endforeach ?></select></label>
            <button class="zl-btn zl-btn-main"><?= e(t('common.save')) ?></button>
        </form>
    <?php elseif ($tab === 'display'): ?>
        <form method="post" action="<?= e(url('settings/display')) ?>" class="zl-stack">
            <?= csrf_field() ?>
            <h1 class="zl-h1"><?= e(t('settings.tab_display')) ?></h1>
            <label class="zl-field"><span><?= e(t('settings.language')) ?></span>
                <select class="zl-input" name="lang"><option value=""><?= e(t('settings.lang_auto')) ?></option><?php foreach (Lang::LANGS as $c => $n): ?><option value="<?= e($c) ?>" <?= $u['lang'] === $c ? 'selected' : '' ?>><?= e($n) ?></option><?php endforeach ?></select></label>
            <div class="zl-field"><span><?= e(t('settings.theme')) ?></span>
                <div class="zl-seg" style="grid-template-columns:repeat(3,1fr)" role="radiogroup" aria-label="<?= e(t('settings.theme')) ?>">
                    <?php foreach (['system' => 'monitor', 'dark' => 'moon', 'light' => 'sun'] as $th => $ic): ?>
                        <input type="radio" class="sr-only" name="theme" value="<?= $th ?>" id="th-<?= $th ?>" <?= $u['theme'] === $th ? 'checked' : '' ?>><label for="th-<?= $th ?>" style="align-items:center;gap:6px"><?= icon($ic) ?><?= e(t('settings.theme_' . $th)) ?></label>
                    <?php endforeach ?>
                </div>
            </div>
            <button class="zl-btn zl-btn-main"><?= e(t('common.save')) ?></button>
        </form>
    <?php else: ?>
        <h1 class="zl-h1"><?= e(t('settings.tab_blocked')) ?></h1>
        <p class="zl-muted" style="margin:6px 0 12px"><?= e(t('settings.blocked_hint')) ?></p>
        <?php if (!$blockedUsers): ?><p class="zl-faint"><?= e(t('settings.blocked_none')) ?></p><?php endif ?>
        <div class="zl-people"><?php foreach ($blockedUsers as $b): ?>
            <div class="zl-person" style="padding-left:0;padding-right:0"><?= avatar_html($b, 'sm') ?><span class="zl-person-info"><b><?= e(display_name($b)) ?></b><small>@<?= e($b['username']) ?></small></span>
                <form method="post" action="<?= e(url('u/' . $b['username'] . '/unblock')) ?>"><?= csrf_field() ?><button class="zl-btn zl-btn-soft zl-btn-sm"><?= e(t('profile.unblock')) ?></button></form></div>
        <?php endforeach ?></div>
    <?php endif ?>
    </div>
</div>
