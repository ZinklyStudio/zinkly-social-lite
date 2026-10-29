<?php
$title = trim((string) setting('landing_title', ''));
$lead = trim((string) setting('landing_lead', '')) ?: (setting('site_description') ?: t('landing.lead'));
?>
<div class="zl-landing">
    <section class="zl-landing-hero">
        <div class="zl-reveal">
            <span class="zl-eyebrow"><?= icon('sparkle', 'zl-i-sm') ?><?= e(t('landing.eyebrow', ['site' => site_name()])) ?></span>
            <h1><?= $title !== '' ? e($title) : t('landing.title_html', ['site' => '<span>' . e(site_name()) . '</span>']) ?></h1>
            <p class="zl-landing-lead"><?= e($lead) ?></p>
            <div class="zl-landing-cta">
                <?php if (setting('registration_open', '1') === '1'): ?><a class="zl-btn zl-btn-main zl-btn-lg" style="width:auto" href="<?= e(url('register')) ?>"><?= e(t('landing.join')) ?></a><?php endif ?>
                <a class="zl-btn zl-btn-soft zl-btn-lg" style="width:auto" href="<?= e(url('login')) ?>"><?= e(t('auth.login')) ?></a>
                <a class="zl-btn zl-btn-ghost zl-btn-lg" style="width:auto" href="<?= e(url('explore')) ?>"><?= icon('compass', 'zl-i-sm') ?><?= e(t('landing.look_around')) ?></a>
            </div>
            <?php if (!empty($stats)): ?>
            <div class="zl-landing-stats">
                <div><b data-count="<?= (int) $stats['members'] ?>"><?= e(short_number($stats['members'])) ?></b><small><?= e(t('landing.s_members')) ?></small></div>
                <div><b data-count="<?= (int) $stats['posts'] ?>"><?= e(short_number($stats['posts'])) ?></b><small><?= e(t('landing.s_posts')) ?></small></div>
                <div><b data-count="<?= (int) $stats['groups'] ?>"><?= e(short_number($stats['groups'])) ?></b><small><?= e(t('landing.s_groups')) ?></small></div>
            </div>
            <?php endif ?>
        </div>
        <div class="zl-card zl-mock zl-float" aria-hidden="true">
            <div class="zl-post" style="padding:4px">
                <div class="zl-post-head"><span class="zl-av zl-av-md">AK</span><div class="zl-post-who"><b class="zl-strong">Alex Kim</b><div class="zl-post-meta"><span><?= e(t('time.m', ['n' => 5])) ?></span> · <?= icon('globe') ?></div></div></div>
                <div class="zl-post-body is-short"><?= e(t('landing.sample')) ?> <span class="zl-tag">#<?= e(t('landing.sample_tag')) ?></span></div>
                <div class="zl-gallery n2" style="margin:12px 0 4px;border-radius:14px"><a style="background:linear-gradient(150deg,#4c1d95,#06b6d4)"></a><a style="background:linear-gradient(150deg,#9d174d,#f97316)"></a></div>
                <div class="zl-actions"><span class="zl-act is-liked"><?= icon('heart') ?><span>128</span></span><span class="zl-act"><?= icon('comment') ?><span>24</span></span><span class="zl-act"><?= icon('repeat') ?><span>9</span></span></div>
            </div>
        </div>
    </section>
    <?php if (setting('landing_features', '1') === '1'): ?>
    <section class="zl-feats">
        <?php foreach ([['users', 'feed'], ['camera', 'stories'], ['chat', 'messages'], ['users', 'groups'], ['page', 'pages'], ['edit', 'rich'], ['shield', 'privacy'], ['mask', 'anon'], ['globe', 'langs']] as [$ic, $k]): ?>
            <div class="zl-card zl-feat zl-reveal"><span class="zl-feat-ico"><?= icon($ic) ?></span><b><?= e(t('landing.f_' . $k)) ?></b><p><?= e(t('landing.f_' . $k . '_text')) ?></p></div>
        <?php endforeach ?>
    </section>
    <?php endif ?>
    <?= Ext::html('landing') ?>
</div>
