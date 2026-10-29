<?php
$sw = fn ($k, $def = '1') => setting($k, $def) === '1' ? 'checked' : '';
$toggle = function (string $k, string $def = '1') use ($sw) {
    return '<label class="zl-check"><span>' . e(t('admin.f_' . $k)) . '<small>' . e(t('admin.f_' . $k . '_hint')) . '</small></span>'
        . '<span class="zl-switch"><input type="checkbox" name="' . e($k) . '" value="1" ' . $sw($k, $def) . '><span></span></span></label>';
};
$sections = ['general' => 'settings', 'landing' => 'home', 'features' => 'check', 'content' => 'edit', 'look' => 'sun', 'limits' => 'chart', 'mail' => 'send', 'legal' => 'file'];
?>
<div class="zl-settings zl-admin">
    <?php View::partial('admin-nav', ['anav' => $anav]); ?>
    <form class="zl-stack" method="post" action="<?= e(url('admin/settings')) ?>" enctype="multipart/form-data" data-settings-form>
        <?= csrf_field() ?><input type="hidden" name="_section" value="" data-section-input>
        <div class="zl-card zl-pad zl-between zl-sticky-head">
            <div><h1 class="zl-h1"><?= e(t('admin.settings')) ?></h1><p class="zl-faint"><?= e(t('admin.settings_hint')) ?></p></div>
            <button class="zl-btn zl-btn-main"><?= icon('check', 'zl-i-sm') ?><?= e(t('common.save')) ?></button>
        </div>
        <nav class="zl-chips zl-section-nav" aria-label="<?= e(t('admin.settings')) ?>">
            <?php foreach ($sections as $k => $ic): ?><a class="zl-chip" href="#<?= $k ?>"><?= icon($ic, 'zl-i-sm') ?><?= e(t('admin.sec_' . $k)) ?></a><?php endforeach ?>
        </nav>

        <div class="zl-admin-grid">
            <section class="zl-card zl-pad zl-stack" id="general">
                <h2 class="zl-h2"><?= icon('settings', 'zl-i-sm') ?> <?= e(t('admin.sec_general')) ?></h2>
                <div class="zl-grid2">
                    <label class="zl-field"><span><?= e(t('install.site_name')) ?></span><input class="zl-input" name="site_name" value="<?= e(site_name()) ?>" maxlength="80" required></label>
                    <label class="zl-field"><span><?= e(t('install.default_lang')) ?></span><select class="zl-input" name="default_lang"><?php foreach (Lang::LANGS as $c => $n): ?><option value="<?= e($c) ?>" <?= setting('default_lang', 'en') === $c ? 'selected' : '' ?>><?= e($n) ?></option><?php endforeach ?></select></label>
                </div>
                <label class="zl-field"><span><?= e(t('admin.site_description')) ?></span><textarea class="zl-input" name="site_description" maxlength="300" rows="3"><?= e((string) setting('site_description', '')) ?></textarea></label>
                <div class="zl-row"><span class="zl-logo" style="width:56px;height:56px;font-size:24px"><?php if (setting('logo')): ?><img src="<?= e(upload_url(setting('logo'))) ?>" alt=""><?php else: ?>Z<?php endif ?></span>
                    <label class="zl-field" style="flex:1"><span><?= e(t('admin.logo')) ?></span><input class="zl-input" type="file" name="logo" accept="image/png,image/jpeg,image/webp"></label></div>
                <?php if (setting('logo')): ?><label class="zl-row" style="font-size:14px"><input type="checkbox" name="remove_logo" value="1"> <?= e(t('admin.remove_logo')) ?></label><?php endif ?>
                <?= $toggle('force_default_lang', '0') ?>
            </section>

            <section class="zl-card zl-pad zl-stack" id="landing">
                <h2 class="zl-h2"><?= icon('home', 'zl-i-sm') ?> <?= e(t('admin.sec_landing')) ?></h2>
                <div class="zl-field"><span><?= e(t('admin.landing_mode')) ?></span>
                    <div class="zl-seg zl-seg3" role="radiogroup" aria-label="<?= e(t('admin.landing_mode')) ?>">
                        <?php foreach (['landing' => 'home', 'login' => 'user', 'explore' => 'compass'] as $m => $ic): ?>
                            <input type="radio" class="sr-only" name="landing_mode" value="<?= $m ?>" id="lm-<?= $m ?>" <?= setting('landing_mode', 'landing') === $m ? 'checked' : '' ?>>
                            <label for="lm-<?= $m ?>"><?= icon($ic, 'zl-i-sm') ?> <?= e(t('admin.lm_' . $m)) ?><small><?= e(t('admin.lm_' . $m . '_hint')) ?></small></label>
                        <?php endforeach ?>
                    </div>
                </div>
                <label class="zl-field"><span><?= e(t('admin.landing_title')) ?></span><input class="zl-input" name="landing_title" maxlength="120" value="<?= e((string) setting('landing_title', '')) ?>" placeholder="<?= e(strip_tags(t('landing.title_html', ['site' => site_name()]))) ?>"></label>
                <label class="zl-field"><span><?= e(t('admin.landing_lead')) ?></span><textarea class="zl-input" name="landing_lead" maxlength="300" rows="2" placeholder="<?= e(t('landing.lead')) ?>"><?= e((string) setting('landing_lead', '')) ?></textarea></label>
                <?= $toggle('landing_features') ?>
                <?= $toggle('landing_stats') ?>
                <a class="zl-btn zl-btn-soft zl-btn-sm" href="<?= e(url('')) ?>" data-preview-landing target="_blank" rel="noopener"><?= icon('eye', 'zl-i-sm') ?><?= e(t('admin.landing_preview')) ?></a>
            </section>

            <section class="zl-card zl-pad zl-stack zl-span-all" id="features">
                <h2 class="zl-h2"><?= icon('check', 'zl-i-sm') ?> <?= e(t('admin.sec_features')) ?></h2>
                <div class="zl-switch-grid">
                    <?php foreach (['registration_open', 'stories_enabled', 'groups_enabled', 'pages_enabled', 'messages_enabled', 'comments_enabled', 'extensions_enabled', 'noindex'] as $k): ?>
                        <?= $toggle($k, $k === 'noindex' ? '0' : '1') ?>
                    <?php endforeach ?>
                    <?= $toggle('maintenance', '0') ?>
                </div>
                <label class="zl-field"><span><?= e(t('admin.maintenance_text')) ?></span><textarea class="zl-input" name="maintenance_text" maxlength="1000" rows="2" placeholder="<?= e(t('maint.text', ['site' => site_name()])) ?>"><?= e((string) setting('maintenance_text', '')) ?></textarea></label>
            </section>

            <section class="zl-card zl-pad zl-stack" id="content">
                <h2 class="zl-h2"><?= icon('edit', 'zl-i-sm') ?> <?= e(t('admin.sec_content')) ?></h2>
                <?= $toggle('rich_text') ?>
                <?php if (!Text::richAvailable()): ?><p class="zl-alert zl-alert-warn"><?= e(t('admin.no_dom')) ?></p><?php endif ?>
                <?= $toggle('anonymous_posts') ?>
                <label class="zl-field"><span><?= e(t('admin.max_post_chars')) ?></span><input class="zl-input" type="number" min="100" max="20000" name="max_post_chars" value="<?= (int) setting('max_post_chars', '5000') ?>"></label>
            </section>

            <section class="zl-card zl-pad zl-stack" id="look">
                <h2 class="zl-h2"><?= icon('sun', 'zl-i-sm') ?> <?= e(t('admin.sec_look')) ?></h2>
                <div class="zl-field"><span><?= e(t('admin.accent')) ?></span>
                    <div class="zl-swatches" role="radiogroup" aria-label="<?= e(t('admin.accent')) ?>">
                        <?php foreach (['violet', 'blue', 'green', 'pink', 'orange', 'red'] as $c): ?>
                            <input type="radio" class="sr-only" name="accent" value="<?= $c ?>" id="ac-<?= $c ?>" <?= setting('accent', 'violet') === $c ? 'checked' : '' ?> data-accent-pick>
                            <label for="ac-<?= $c ?>" class="zl-swatch sw-<?= $c ?>" title="<?= e(t('admin.c_' . $c)) ?>"><span class="sr-only"><?= e(t('admin.c_' . $c)) ?></span></label>
                        <?php endforeach ?>
                    </div>
                </div>
                <label class="zl-field"><span><?= e(t('admin.guest_theme')) ?></span><select class="zl-input" name="guest_theme"><?php foreach (['system', 'dark', 'light'] as $th): ?><option value="<?= $th ?>" <?= setting('guest_theme', 'system') === $th ? 'selected' : '' ?>><?= e(t('settings.theme_' . $th)) ?></option><?php endforeach ?></select></label>
                <?= $toggle('animations') ?>
            </section>

            <section class="zl-card zl-pad zl-stack" id="limits">
                <h2 class="zl-h2"><?= icon('chart', 'zl-i-sm') ?> <?= e(t('admin.limits')) ?></h2>
                <div class="zl-grid2">
                    <label class="zl-field"><span><?= e(t('admin.max_upload')) ?></span><input class="zl-input" type="number" min="1" max="64" name="max_upload_mb" value="<?= (int) setting('max_upload_mb', '8') ?>"><small class="zl-hint"><?= e(t('admin.server_limit', ['mb' => (int) ini_get('upload_max_filesize')])) ?></small></label>
                    <label class="zl-field"><span><?= e(t('admin.max_images')) ?></span><input class="zl-input" type="number" min="1" max="10" name="max_post_images" value="<?= (int) setting('max_post_images', '4') ?>"></label>
                </div>
                <?= $toggle('story_video') ?>
                <div class="zl-grid2">
                    <label class="zl-field"><span><?= e(t('admin.story_video_mb')) ?></span><input class="zl-input" type="number" min="1" max="200" name="story_video_mb" value="<?= Stories::videoMaxMb() ?>"><small class="zl-hint"><?= e(t('admin.server_limit', ['mb' => (int) ini_get('upload_max_filesize')])) ?></small></label>
                    <label class="zl-field"><span><?= e(t('admin.story_video_seconds')) ?></span><input class="zl-input" type="number" min="5" max="600" name="story_video_seconds" value="<?= max(5, (int) setting('story_video_seconds', '60')) ?>"></label>
                </div>
            </section>

            <?php $mm = (string) setting('mail_method', 'php'); $enc = (string) setting('smtp_encryption', 'tls'); $lastErr = (string) setting('mail_last_error', ''); ?>
            <section class="zl-card zl-pad zl-stack zl-span-all" id="mail" data-mail-box data-test-url="<?= e(url('admin/settings/mail-test')) ?>">
                <h2 class="zl-h2"><?= icon('send', 'zl-i-sm') ?> <?= e(t('admin.sec_mail')) ?></h2>
                <p class="zl-faint"><?= e(t('mail.intro')) ?></p>
                <div class="zl-field"><span><?= e(t('mail.method')) ?></span>
                    <div class="zl-seg zl-seg3" role="radiogroup" aria-label="<?= e(t('mail.method')) ?>">
                        <?php foreach (['smtp' => 'send', 'php' => 'globe', 'off' => 'eye-off'] as $m => $ic): ?>
                            <input type="radio" class="sr-only" name="mail_method" value="<?= $m ?>" id="mm-<?= $m ?>" <?= $mm === $m ? 'checked' : '' ?>>
                            <label for="mm-<?= $m ?>"><?= icon($ic, 'zl-i-sm') ?> <?= e(t('mail.method_' . $m)) ?><small><?= e(t('mail.method_' . $m . '_hint')) ?></small></label>
                        <?php endforeach ?>
                    </div>
                </div>
                <div class="zl-grid2">
                    <label class="zl-field"><span><?= e(t('admin.mail_from')) ?></span><input class="zl-input" type="email" name="mail_from" value="<?= e((string) setting('mail_from', '')) ?>" placeholder="no-reply@<?= e(preg_replace('/^www\.|:\d+$/', '', (string) ($_SERVER['HTTP_HOST'] ?? 'your-site.com'))) ?>"><small class="zl-hint"><?= e(t('admin.mail_from_hint')) ?></small></label>
                    <label class="zl-field"><span><?= e(t('mail.from_name')) ?></span><input class="zl-input" name="mail_from_name" maxlength="80" value="<?= e((string) setting('mail_from_name', '')) ?>" placeholder="<?= e(site_name()) ?>"></label>
                </div>
                <div class="zl-stack" data-smtp-fields <?= $mm === 'smtp' ? '' : 'hidden' ?>>
                    <div class="zl-mail-presets"><small class="zl-faint"><?= e(t('mail.presets')) ?></small>
                        <?php foreach (['Gmail' => 'smtp.gmail.com|587|tls', 'Outlook' => 'smtp.office365.com|587|tls', 'Zoho' => 'smtp.zoho.com|465|ssl', 'Brevo' => 'smtp-relay.brevo.com|587|tls', 'Mailgun' => 'smtp.mailgun.org|587|tls', 'SendGrid' => 'smtp.sendgrid.net|587|tls'] as $pn => $pv): ?>
                        <button type="button" class="zl-chip" data-smtp-preset="<?= e($pv) ?>"><?= e($pn) ?></button>
                        <?php endforeach ?>
                    </div>
                    <div class="zl-grid-smtp">
                        <label class="zl-field"><span><?= e(t('mail.host')) ?></span><input class="zl-input" name="smtp_host" value="<?= e((string) setting('smtp_host', '')) ?>" placeholder="mail.your-site.com" autocomplete="off" spellcheck="false"></label>
                        <label class="zl-field"><span><?= e(t('mail.port')) ?></span><input class="zl-input" type="number" min="1" max="65535" name="smtp_port" value="<?= (int) setting('smtp_port', '587') ?>"></label>
                        <label class="zl-field"><span><?= e(t('mail.encryption')) ?></span><select class="zl-input" name="smtp_encryption">
                            <?php foreach (['tls' => 'STARTTLS', 'ssl' => 'SSL/TLS', 'none' => t('mail.enc_none')] as $ek => $ev): ?><option value="<?= $ek ?>" <?= $enc === $ek ? 'selected' : '' ?>><?= e($ev) ?></option><?php endforeach ?>
                        </select></label>
                    </div>
                    <small class="zl-hint"><?= e(t('mail.port_hint')) ?></small>
                    <div class="zl-grid2">
                        <label class="zl-field"><span><?= e(t('mail.user')) ?></span><input class="zl-input" name="smtp_user" value="<?= e((string) setting('smtp_user', '')) ?>" autocomplete="off" spellcheck="false"></label>
                        <label class="zl-field"><span><?= e(t('mail.pass')) ?></span><input class="zl-input" type="password" name="smtp_pass" value="" autocomplete="new-password" placeholder="<?= setting('smtp_pass', '') !== '' ? e(t('mail.pass_saved')) : '' ?>">
                            <?php if (setting('smtp_pass', '') !== ''): ?><small class="zl-hint"><?= e(t('mail.pass_keep')) ?></small><?php endif ?></label>
                    </div>
                </div>
                <div class="zl-mail-test">
                    <button type="button" class="zl-btn zl-btn-soft" data-mail-check><?= icon('check', 'zl-i-sm') ?><?= e(t('mail.test')) ?></button>
                    <input class="zl-input" type="email" name="test_to" value="<?= e((string) (Auth::user()['email'] ?? '')) ?>" aria-label="<?= e(t('mail.test_to')) ?>" placeholder="<?= e(t('mail.test_to')) ?>">
                    <button type="button" class="zl-btn zl-btn-soft" data-mail-send><?= icon('send', 'zl-i-sm') ?><?= e(t('mail.send_test')) ?></button>
                </div>
                <div class="zl-db-result" data-mail-result hidden role="status" aria-live="polite"></div>
                <?php if ($lastErr !== ''): ?><p class="zl-alert zl-alert-error zl-mail-last"><span><b><?= e(t('mail.last_error')) ?></b> <?= e($lastErr) ?></span></p>
                <?php elseif (setting('mail_last_ok')): ?><p class="zl-faint zl-mail-last"><?= e(t('mail.last_ok', ['when' => date('Y-m-d H:i', (int) setting('mail_last_ok'))])) ?></p><?php endif ?>
            </section>

            <section class="zl-card zl-pad zl-stack zl-span-all" id="legal">
                <h2 class="zl-h2"><?= icon('file', 'zl-i-sm') ?> <?= e(t('admin.pages')) ?></h2>
                <p class="zl-faint"><?= e(t('admin.legal_hint')) ?> <a class="zl-link" href="<?= e(url('admin/site-pages')) ?>"><?= e(t('admin.site_pages')) ?></a></p>
                <div class="zl-grid2">
                    <label class="zl-field"><span><?= e(t('page.terms')) ?></span><textarea class="zl-input" name="terms_text" rows="8" placeholder="<?= e(t('admin.default_text')) ?>"><?= e((string) setting('terms_text', '')) ?></textarea></label>
                    <label class="zl-field"><span><?= e(t('page.privacy')) ?></span><textarea class="zl-input" name="privacy_text" rows="8" placeholder="<?= e(t('admin.default_text')) ?>"><?= e((string) setting('privacy_text', '')) ?></textarea></label>
                </div>
            </section>
        </div>
        <div class="zl-save-bar"><button class="zl-btn zl-btn-main"><?= icon('check', 'zl-i-sm') ?><?= e(t('common.save')) ?></button></div>
    </form>
</div>
