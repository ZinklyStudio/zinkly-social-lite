<?php /** A page in a grid. $pg, $following (bool) */ $me = Auth::id(); ?>
<div class="zl-card zl-group-card zl-page-card">
    <a class="zl-group-cover" href="<?= e(url('pages/' . $pg['slug'])) ?>" tabindex="-1" aria-hidden="true"><?php if ($pg['cover']): ?><img src="<?= e(upload_url($pg['cover'])) ?>" alt="" loading="lazy"><?php endif ?></a>
    <div class="zl-pad">
        <div class="zl-row" style="align-items:flex-start">
            <a class="zl-card-av" href="<?= e(url('pages/' . $pg['slug'])) ?>"><?= Pages::avatar($pg, 'md') ?></a>
            <div style="min-width:0;flex:1">
                <a class="zl-strong" href="<?= e(url('pages/' . $pg['slug'])) ?>"><?= e($pg['name']) ?></a><?php if ($pg['verified']): ?> <?= icon('verified', 'zl-verified') ?><?php endif ?>
                <p class="zl-faint"><?= e(t('pages.cat_' . $pg['category'])) ?> · <span data-page-followers><?= e(tc('pages.n_followers', (int) $pg['followers_count'])) ?></span></p>
            </div>
        </div>
        <?php if ($pg['description'] !== ''): ?><p class="zl-muted" style="font-size:14px;margin-top:8px"><?= e(str_limit($pg['description'], 110)) ?></p><?php endif ?>
        <?php if ($me): ?>
        <form method="post" action="<?= e(url('pages/' . $pg['slug'] . '/follow')) ?>" data-page-follow style="margin-top:12px"><?= csrf_field() ?>
            <button class="zl-btn zl-btn-sm zl-btn-block <?= !empty($following) ? 'zl-btn-soft' : 'zl-btn-main' ?>" data-l-on="<?= e(t('pages.following')) ?>" data-l-off="<?= e(t('pages.follow')) ?>"><?= e(!empty($following) ? t('pages.following') : t('pages.follow')) ?></button>
        </form>
        <?php endif ?>
    </div>
</div>
