<?php $me = Auth::user(); if (!$me) { return; } $pid = (int) $p['id']; ?>
<form class="zl-comment-form" method="post" action="<?= e(url('p/' . $pid . '/comment')) ?>" data-comment-form="<?= $pid ?>">
    <?= csrf_field() ?>
    <input type="hidden" name="parent_id" value="">
    <?= avatar_html($me, 'sm') ?>
    <label class="sr-only" for="cf-<?= $pid ?>"><?= e(t('comment.write')) ?></label>
    <textarea id="cf-<?= $pid ?>" class="zl-input" name="body" rows="1" maxlength="<?= Posts::MAX_COMMENT ?>" placeholder="<?= e(t('comment.write')) ?>" required></textarea>
    <button class="zl-icon-btn" aria-label="<?= e(t('comment.send')) ?>"><?= icon('send') ?></button>
</form>
