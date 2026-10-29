<?php
/** One comment with its replies. $c = comment (author, liked, replies), $p = post, $reply = bool */
$me = Auth::id();
$a = $c['author'];
$cid = (int) $c['id'];
$canDel = $me && ((int) $c['user_id'] === $me || (int) $p['user_id'] === $me || Auth::isMod());
?>
<div class="zl-comment" id="c<?= $cid ?>" data-comment="<?= $cid ?>">
    <a href="<?= e(url('u/' . $a['username'])) ?>" aria-label="<?= e(display_name($a)) ?>"><?= avatar_html($a, $reply ? 'xs' : 'sm') ?></a>
    <div class="zl-comment-bubble">
        <div class="zl-comment-box">
            <a class="zl-strong" href="<?= e(url('u/' . $a['username'])) ?>"><?= e(display_name($a)) ?></a><?php if ($a['verified']): ?> <?= icon('verified', 'zl-verified') ?><?php endif ?><?= Pro::badge($a) ?>
            <p><?= Text::render($c['body']) ?></p>
        </div>
        <div class="zl-comment-tools">
            <time datetime="<?= e(date('c', (int) $c['created_at'])) ?>"><?= e(ago((int) $c['created_at'])) ?></time>
            <?php if ($me): ?>
                <button type="button" class="<?= $c['liked'] ? 'is-liked' : '' ?>" data-clike="<?= e(url('c/' . $cid . '/like')) ?>" aria-pressed="<?= $c['liked'] ? 'true' : 'false' ?>"><?= e(t('post.like')) ?><span data-n><?= $c['likes_count'] ? ' · ' . (int) $c['likes_count'] : '' ?></span></button>
                <button type="button" data-reply="<?= $reply ? (int) $c['parent_id'] : $cid ?>" data-reply-name="<?= e($a['username']) ?>"><?= e(t('comment.reply')) ?></button>
                <?php if ((int) $c['user_id'] !== $me): ?><button type="button" data-report="comment:<?= $cid ?>"><?= e(t('report.short')) ?></button><?php endif ?>
                <?php if ($canDel): ?><button type="button" data-delete-comment="<?= e(url('c/' . $cid . '/delete')) ?>"><?= e(t('post.delete')) ?></button><?php endif ?>
            <?php endif ?>
        </div>
        <?php if (!$reply): ?>
        <div class="zl-replies" data-replies="<?= $cid ?>"><?php foreach ($c['replies'] ?? [] as $r) { echo View::fetch('partials/comment', ['c' => $r, 'p' => $p, 'reply' => true]); } ?></div>
        <?php endif ?>
    </div>
</div>
