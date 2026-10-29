<div class="zl-col">
    <form class="zl-search" action="<?= e(url('search')) ?>" method="get" role="search">
        <?= icon('search') ?>
        <label class="sr-only" for="q-main"><?= e(t('nav.search')) ?></label>
        <input id="q-main" class="zl-input" name="q" value="<?= e($q) ?>" placeholder="<?= e(t('search.placeholder')) ?>" autofocus>
        <input type="hidden" name="type" value="<?= e($type) ?>">
    </form>
    <div class="zl-card">
        <nav class="zl-tabs" aria-label="<?= e(t('nav.search')) ?>">
            <?php foreach (['people', 'posts', 'tags', 'groups', 'pages'] as $tp): if (($tp === 'groups' && setting('groups_enabled', '1') !== '1') || ($tp === 'pages' && !Pages::enabled())) { continue; } ?>
                <a href="<?= e(url('search', ['q' => $q, 'type' => $tp])) ?>" class="<?= $type === $tp ? 'is-on' : '' ?>"><?= e(t('search.' . $tp)) ?></a>
            <?php endforeach ?>
        </nav>
    </div>
    <?php if (mb_strlen($q) < 2): ?>
        <div class="zl-card zl-empty"><?= icon('search') ?><b><?= e(t('search.start')) ?></b><span><?= e(t('search.start_hint')) ?></span></div>
    <?php elseif (!$results): ?>
        <div class="zl-card zl-empty"><?= icon('search') ?><b><?= e(t('search.none', ['q' => $q])) ?></b></div>
    <?php elseif ($type === 'posts'): ?>
        <?php foreach ($results as $p) { View::partial('post', ['p' => $p]); } ?>
    <?php elseif ($type === 'tags'): ?>
        <div class="zl-card"><?php foreach ($results as $h): ?><a class="zl-trend" href="<?= e(url('hashtag/' . rawurlencode($h['tag']))) ?>"><span class="zl-tag">#<?= e($h['tag']) ?></span><small><?= e(tc('post.n_posts', (int) $h['posts_count'])) ?></small></a><?php endforeach ?></div>
    <?php elseif ($type === 'pages'): ?>
        <div class="zl-groups-grid"><?php $fp = Pages::followedIds(Auth::id()); foreach ($results as $pg) { View::partial('page-card', ['pg' => $pg, 'following' => in_array((int) $pg['id'], $fp, true)]); } ?></div>
    <?php elseif ($type === 'groups'): ?>
        <div class="zl-groups-grid"><?php foreach ($results as $g) { View::partial('group-card', ['g' => $g]); } ?></div>
    <?php else: ?>
        <div class="zl-card zl-people"><?php foreach ($results as $pp) { View::partial('person', ['p' => $pp]); } ?></div>
    <?php endif ?>
</div>
