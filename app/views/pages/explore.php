<div class="zl-col">
    <div class="zl-card">
        <nav class="zl-tabs" aria-label="<?= e(t('nav.explore')) ?>">
            <a href="<?= e(url('explore')) ?>" class="<?= !$popular ? 'is-on' : '' ?>"><?= e(t('explore.latest')) ?></a>
            <a href="<?= e(url('explore', ['tab' => 'popular'])) ?>" class="<?= $popular ? 'is-on' : '' ?>"><?= e(t('explore.popular')) ?></a>
            <a href="<?= e(url('search')) ?>"><?= e(t('nav.search')) ?></a>
        </nav>
    </div>
    <?php if ($trending): ?>
    <div class="zl-chips" aria-label="<?= e(t('explore.trending')) ?>">
        <?php foreach ($trending as $h): ?><a class="zl-btn zl-btn-soft zl-btn-sm" href="<?= e(url('hashtag/' . rawurlencode($h['tag']))) ?>">#<?= e($h['tag']) ?></a><?php endforeach ?>
    </div>
    <?php endif ?>
</div>
<div style="height:14px"></div>
<?php View::partial('feed', ['posts' => $posts, 'scope' => $scope]); ?>
