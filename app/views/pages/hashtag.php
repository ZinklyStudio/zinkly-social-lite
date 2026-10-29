<div class="zl-col">
    <div class="zl-card zl-pad zl-row">
        <span class="zl-logo" style="width:48px;height:48px"><?= icon('hash') ?></span>
        <div><h1 class="zl-h1">#<?= e($tag) ?></h1><p class="zl-faint"><?= e(tc('post.n_posts', $count)) ?></p></div>
    </div>
</div>
<div style="height:14px"></div>
<?php View::partial('feed', ['posts' => $posts, 'scope' => $scope, 'emptyIcon' => 'hash', 'emptyTitle' => t('feed.tag_empty')]); ?>
