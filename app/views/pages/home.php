<div class="zl-col">
    <?php if ($tray || setting('stories_enabled', '1') === '1') { View::partial('stories', ['tray' => $tray]); } ?>
    <?php View::partial('composer'); ?>
</div>
<div style="height:14px"></div>
<?php View::partial('feed', ['posts' => $posts, 'scope' => $scope, 'emptyIcon' => 'users', 'emptyTitle' => t('feed.home_empty'), 'emptyText' => t('feed.home_empty_hint')]); ?>
