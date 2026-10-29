<div class="zl-modal" id="composer-modal" hidden role="dialog" aria-modal="true" aria-labelledby="composer-title">
    <div class="zl-sheet">
        <div class="zl-sheet-head"><b id="composer-title"><?= e(t('post.create')) ?></b><button type="button" class="zl-icon-btn" data-close aria-label="<?= e(t('common.close')) ?>"><?= icon('x') ?></button></div>
        <div class="zl-sheet-body"><?php View::partial('composer', ['idSuffix' => 'modal']); ?></div>
    </div>
</div>
