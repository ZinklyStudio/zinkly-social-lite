<div class="<?= Auth::check() ? 'zl-col zl-col-wide' : 'zl-auth' ?>" style="max-width:900px">
    <article class="zl-card zl-pad zl-article">
        <h1 class="zl-h1"><?= e($title) ?></h1>
        <?php if (!empty($sitePage) && !$sitePage['published']): ?><p class="zl-alert zl-alert-warn" style="margin-top:10px"><?= e(t('sitepages.draft_note')) ?></p><?php endif ?>
        <div class="zl-post-body zl-rich" style="margin-top:14px"><?= isset($html) ? $html : Text::render($body) ?></div>
        <?php if (!empty($sitePage) && Auth::isAdmin()): ?><p style="margin-top:18px"><a class="zl-btn zl-btn-soft zl-btn-sm" href="<?= e(url('admin/site-pages', ['edit' => $sitePage['id']])) ?>"><?= icon('edit', 'zl-i-sm') ?><?= e(t('post.edit')) ?></a></p><?php endif ?>
    </article>
</div>
