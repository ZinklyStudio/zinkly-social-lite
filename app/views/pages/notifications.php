<?php $icons = ['follow' => 'user', 'follow_request' => 'user', 'follow_accepted' => 'check', 'reaction' => 'heart', 'comment' => 'comment', 'reply' => 'comment',
    'mention' => 'chat', 'share' => 'repeat', 'comment_like' => 'heart', 'group_join' => 'users', 'group_post' => 'users',
    'group_post_pending' => 'clock', 'group_post_approved' => 'check', 'page_follow' => 'page']; ?>
<div class="zl-col">
    <div class="zl-card zl-pad zl-between"><h1 class="zl-h1"><?= e(t('nav.notifications')) ?></h1>
        <?php if ($requests): ?><a class="zl-btn zl-btn-soft zl-btn-sm" href="<?= e(url('u/' . Auth::user()['username'])) ?>"><?= e(tc('notif.n_requests', $requests)) ?></a><?php endif ?>
    </div>
    <?php if (!$items): ?>
        <div class="zl-card zl-empty"><?= icon('bell') ?><b><?= e(t('notif.empty')) ?></b><span><?= e(t('notif.empty_hint')) ?></span></div>
    <?php else: ?>
        <div class="zl-card" style="overflow:hidden">
            <?php foreach ($items as $n): ?>
                <a class="zl-notif<?= $n['read_at'] === null ? ' is-new' : '' ?>" href="<?= e($n['link']) ?>">
                    <?= avatar_html($n['actor'], 'md') ?><span class="zl-notif-ico"><?= icon($icons[$n['type']] ?? 'bell') ?></span>
                    <p><?= e($n['text']) ?><small><?= e(ago((int) $n['created_at'])) ?></small></p>
                </a>
            <?php endforeach ?>
        </div>
        <?php if (count($items) >= 40): ?><div class="zl-center"><a class="zl-btn zl-btn-soft" href="<?= e(url('notifications', ['before' => end($items)['id']])) ?>"><?= e(t('feed.more')) ?></a></div><?php endif ?>
    <?php endif ?>
</div>
