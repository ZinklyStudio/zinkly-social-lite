<div class="zl-col"><div class="zl-card zl-pad"><h1 class="zl-h1"><?= e(t('nav.saved')) ?></h1><p class="zl-faint"><?= e(t('saved.hint')) ?></p></div></div>
<div style="height:14px"></div>
<?php View::partial('feed', ['posts' => $posts, 'scope' => $scope, 'emptyIcon' => 'bookmark', 'emptyTitle' => t('saved.empty'), 'emptyText' => t('saved.empty_hint')]); ?>
