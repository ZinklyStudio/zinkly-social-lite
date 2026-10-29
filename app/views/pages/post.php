<div class="zl-col">
    <?php View::partial('post', ['p' => $p]); ?>
 <?php if (setting('comments_enabled', '1') === '1' && $p['status'] === 'published'): ?>
    <section class="zl-card zl-pad" id="comments" aria-label="<?= e(t('post.comments')) ?>">
        <h2 class="zl-h2" style="margin-bottom:12px"><?= e(t('post.comments')) ?></h2>
        <div class="zl-comments-list" data-static-comments="<?= (int) $p['id'] ?>">
            <?php foreach ($comments as $c) { View::partial('comment', ['c' => $c, 'p' => $p, 'reply' => false]); } ?>
            <?php if (!$comments): ?><p class="zl-faint" data-no-comments><?= e(t('comment.none')) ?></p><?php endif ?>
        </div>
        <?php if (Auth::check()) { View::partial('comment-form', ['p' => $p]); } else { ?><p class="zl-muted" style="margin-top:12px"><a class="zl-link" href="<?= e(url('login')) ?>"><?= e(t('comment.login')) ?></a></p><?php } ?>
    </section>
    <?php endif ?>
</div>
