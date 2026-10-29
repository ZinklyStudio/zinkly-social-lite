<?php
/** A list of posts with "load more" (infinite scroll). $posts, $scope ('' = no more pages), $emptyTitle, $emptyText */
?>
<div class="zl-col<?= !empty($wide) ? ' zl-col-wide' : '' ?>" data-feed="<?= e($scope ?? '') ?>" data-more-url="<?= e(url('feed/more')) ?>">
    <?php foreach ($posts as $p) { View::partial('post', ['p' => $p]); } ?>
    <?php if (!$posts): ?>
        <div class="zl-card zl-empty"><?= icon($emptyIcon ?? 'compass') ?><b><?= e($emptyTitle ?? t('feed.empty')) ?></b><?php if (($emptyText ?? null) !== ''): ?><span><?= e($emptyText ?? t('feed.empty_hint')) ?></span><?php endif ?></div>
    <?php elseif (!empty($scope) && count($posts) >= Posts::PAGE): ?>
        <div class="zl-center" data-more-wrap><button type="button" class="zl-btn zl-btn-soft" data-more data-before="<?= (int) end($posts)['id'] ?>"><?= e(t('feed.more')) ?></button></div>
    <?php endif ?>
</div>
