<?php
/** Camera button that uploads a new photo or cover right away (and a remove button). $action URL, $type 'avatar'|'cover', $has bool, $target CSS selector of the box to refresh. */
$isCover = $type === 'cover';
?>
<div class="zl-photo-edit is-<?= e($type) ?>">
    <form method="post" action="<?= e($action) ?>" enctype="multipart/form-data" data-photo-form data-target="<?= e($target) ?>" data-type="<?= e($type) ?>">
        <?= csrf_field() ?><input type="hidden" name="type" value="<?= e($type) ?>">
        <label class="zl-photo-btn" title="<?= e($isCover ? t('photo.change_cover') : t('photo.change_avatar')) ?>">
            <input class="sr-only" type="file" name="photo" accept="image/jpeg,image/png,image/webp<?= $isCover ? '' : ',image/gif' ?>" data-photo-input>
            <?= icon('camera') ?><?php if ($isCover): ?><span><?= e(t('photo.change_cover')) ?></span><?php else: ?><span class="sr-only"><?= e(t('photo.change_avatar')) ?></span><?php endif ?>
        </label>
        <noscript><button class="zl-btn zl-btn-soft zl-btn-sm"><?= e(t('common.save')) ?></button></noscript>
    </form>
    <?php if ($isCover): ?>
    <form method="post" action="<?= e($action) ?>" data-photo-form data-target="<?= e($target) ?>" data-type="<?= e($type) ?>" data-remove <?= empty($has) ? 'hidden' : '' ?>>
        <?= csrf_field() ?><input type="hidden" name="type" value="<?= e($type) ?>"><input type="hidden" name="remove" value="1">
        <button class="zl-photo-btn is-remove" title="<?= e(t('photo.remove')) ?>" aria-label="<?= e(t('photo.remove')) ?>"><?= icon('trash') ?></button>
    </form>
    <?php endif ?>
</div>
