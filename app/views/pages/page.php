<?php
$me = Auth::id();
$isOwner = (int) $pg['owner_id'] === $me || Auth::isAdmin();
$base = 'pages/' . $pg['slug'];
?>
<div class="zl-col zl-col-wide">
    <section class="zl-card">
        <div class="zl-group-cover zl-cover-lg" id="pg-cover"><?php if ($pg['cover']): ?><img src="<?= e(upload_url($pg['cover'])) ?>" alt=""><?php endif ?>
            <?php if ($isAdmin) { View::partial('photo-edit', ['action' => url($base . '/photo'), 'type' => 'cover', 'has' => (bool) $pg['cover'], 'target' => '#pg-cover']); } ?>
        </div>
        <div class="zl-pad zl-entity-head">
            <span class="zl-av-wrap zl-entity-av" id="pg-avatar"><?= Pages::avatar($pg, 'xl') ?>
                <?php if ($isAdmin) { View::partial('photo-edit', ['action' => url($base . '/photo'), 'type' => 'avatar', 'has' => (bool) $pg['avatar'], 'target' => '#pg-avatar']); } ?>
            </span>
            <div class="zl-between" style="align-items:flex-start;flex-wrap:wrap">
                <div>
                    <h1 class="zl-h1"><?= e($pg['name']) ?><?php if ($pg['verified']): ?> <?= icon('verified', 'zl-verified') ?><?php endif ?></h1>
                    <p class="zl-faint zl-meta-line"><span class="zl-chip-sm"><?= e(t('pages.cat_' . $pg['category'])) ?></span> · <span data-page-followers><?= e(tc('pages.n_followers', (int) $pg['followers_count'])) ?></span> · <?= e(tc('pages.n_posts', (int) $pg['posts_count'])) ?></p>
                </div>
                <div class="zl-row" style="flex-wrap:wrap">
                    <?php if ($me): ?>
                    <form method="post" action="<?= e(url($base . '/follow')) ?>" data-page-follow><?= csrf_field() ?>
                        <button class="zl-btn zl-btn-sm <?= $following ? 'zl-btn-soft' : 'zl-btn-main' ?>" data-l-on="<?= e(t('pages.following')) ?>" data-l-off="<?= e(t('pages.follow')) ?>"><?= e($following ? t('pages.following') : t('pages.follow')) ?></button>
                    </form>
                    <?php else: ?>
                    <a class="zl-btn zl-btn-main zl-btn-sm" href="<?= e(url('login')) ?>"><?= e(t('pages.follow')) ?></a>
                    <?php endif ?>
                    <?php if ($pg['website'] !== ''): ?><a class="zl-btn zl-btn-soft zl-btn-sm" href="<?= e($pg['website']) ?>" target="_blank" rel="nofollow noopener ugc"><?= icon('link', 'zl-i-sm') ?><?= e(t('pages.website')) ?></a><?php endif ?>
                    <button type="button" class="zl-btn zl-btn-soft zl-btn-sm" data-copy="<?= e(full_url($base)) ?>"><?= icon('share', 'zl-i-sm') ?><?= e(t('post.copy_link')) ?></button>
                    <?php if ($isAdmin): ?><button type="button" class="zl-btn zl-btn-soft zl-btn-sm" data-open="page-edit"><?= icon('settings', 'zl-i-sm') ?><?= e(t('pages.manage')) ?></button><?php endif ?>
                    <?php if ($me && !$isAdmin): ?><button type="button" class="zl-icon-btn" data-report="page:<?= (int) $pg['id'] ?>" aria-label="<?= e(t('report.page')) ?>" title="<?= e(t('report.page')) ?>"><?= icon('flag') ?></button><?php endif ?>
                </div>
            </div>
            <?php if ($pg['description'] !== '' && $tab === 'posts'): ?><p class="zl-post-body"><?= Text::render(str_limit($pg['description'], 300)) ?></p><?php endif ?>
        </div>
        <nav class="zl-tabs" style="border-top:1px solid var(--border)" aria-label="<?= e(t('pages.sections')) ?>">
            <a href="<?= e(url($base)) ?>" class="<?= $tab === 'posts' ? 'is-on' : '' ?>"><?= e(t('group.tab_posts')) ?></a>
            <a href="<?= e(url($base, ['tab' => 'about'])) ?>" class="<?= $tab === 'about' ? 'is-on' : '' ?>"><?= e(t('group.tab_about')) ?></a>
        </nav>
    </section>
    <?php if ($tab === 'about'): ?>
        <section class="zl-card zl-pad zl-stack">
            <h2 class="zl-h2"><?= e(t('group.tab_about')) ?></h2>
            <?php if ($pg['description'] !== ''): ?><p class="zl-post-body" style="margin:0"><?= Text::render($pg['description']) ?></p><?php endif ?>
            <ul class="zl-facts">
                <li><?= icon('page') ?><div><b><?= e(t('pages.cat_' . $pg['category'])) ?></b><small><?= e(t('pages.category')) ?></small></div></li>
                <?php if ($pg['website'] !== ''): ?><li><?= icon('link') ?><div><a class="zl-link" href="<?= e($pg['website']) ?>" target="_blank" rel="nofollow noopener ugc"><?= e(preg_replace('~^https?://(www\.)?~', '', $pg['website'])) ?></a></div></li><?php endif ?>
                <li><?= icon('calendar') ?><div><b><?= e(t('group.created', ['date' => Lang::date((int) $pg['created_at'])])) ?></b></div></li>
            </ul>
            <h2 class="zl-h2"><?= e(t('pages.admins')) ?></h2>
            <div class="zl-people zl-card-inset"><?php foreach ($admins as $ad): $au = $ad['user']; ?>
                <div class="zl-person"><?= avatar_html($au, 'sm') ?><a class="zl-person-info" href="<?= e(url('u/' . $au['username'])) ?>"><b><?= e(display_name($au)) ?></b><small>@<?= e($au['username']) ?></small></a>
                    <span class="zl-pill is-admin"><?= e(t('pages.role_' . $ad['role'])) ?></span>
                    <?php if ($isOwner && $ad['role'] !== 'owner'): ?><form method="post" action="<?= e(url($base . '/remove-admin')) ?>"><?= csrf_field() ?><input type="hidden" name="user_id" value="<?= (int) $au['id'] ?>"><button class="zl-btn zl-btn-soft zl-btn-sm"><?= e(t('pages.remove_admin')) ?></button></form><?php endif ?>
                </div>
            <?php endforeach ?></div>
            <?php if ($isOwner): ?>
            <form class="zl-row" method="post" action="<?= e(url($base . '/add-admin')) ?>"><?= csrf_field() ?>
                <label class="sr-only" for="pa-user"><?= e(t('pages.add_admin')) ?></label><input id="pa-user" class="zl-input" name="username" placeholder="@username" required>
                <button class="zl-btn zl-btn-soft"><?= icon('plus', 'zl-i-sm') ?><?= e(t('pages.add_admin')) ?></button>
            </form>
            <?php endif ?>
        </section>
    <?php elseif ($isAdmin): ?>
        <?php View::partial('composer', ['pageId' => (int) $pg['id'], 'page' => $pg, 'idSuffix' => 'page']); ?>
    <?php endif ?>
</div>
<?php if ($tab === 'posts'): ?>
<div style="height:14px"></div>
<?php View::partial('feed', ['posts' => $posts, 'scope' => $scope, 'wide' => true, 'emptyIcon' => 'page', 'emptyTitle' => t('pages.no_posts'), 'emptyText' => '']); ?>
<?php endif ?>

<?php if ($isAdmin): ?>
<div class="zl-modal" id="page-edit" hidden role="dialog" aria-modal="true" aria-labelledby="pe-title">
    <div class="zl-sheet">
        <div class="zl-sheet-head"><b id="pe-title"><?= e(t('pages.manage')) ?></b><button type="button" class="zl-icon-btn" data-close aria-label="<?= e(t('common.close')) ?>"><?= icon('x') ?></button></div>
        <form class="zl-sheet-body" method="post" action="<?= e(url($base . '/edit')) ?>">
            <?= csrf_field() ?>
            <label class="zl-field"><span><?= e(t('pages.name')) ?></span><input class="zl-input" name="name" value="<?= e($pg['name']) ?>" maxlength="80" required></label>
            <label class="zl-field"><span><?= e(t('pages.category')) ?></span><select class="zl-input" name="category"><?php foreach (Pages::CATEGORIES as $c): ?><option value="<?= $c ?>" <?= $pg['category'] === $c ? 'selected' : '' ?>><?= e(t('pages.cat_' . $c)) ?></option><?php endforeach ?></select></label>
            <label class="zl-field"><span><?= e(t('pages.website')) ?></span><input class="zl-input" name="website" value="<?= e($pg['website']) ?>" maxlength="190" inputmode="url"></label>
            <label class="zl-field"><span><?= e(t('pages.about')) ?></span><textarea class="zl-input" name="description" maxlength="1000"><?= e($pg['description']) ?></textarea></label>
            <button class="zl-btn zl-btn-main zl-btn-block"><?= e(t('common.save')) ?></button>
        </form>
        <?php if ($isOwner): ?>
        <form class="zl-sheet-body" style="border-top:1px solid var(--border)" method="post" action="<?= e(url($base . '/delete')) ?>" data-confirm="<?= e(t('pages.delete_confirm')) ?>"><?= csrf_field() ?><button class="zl-btn zl-btn-danger zl-btn-block"><?= icon('trash', 'zl-i-sm') ?><?= e(t('pages.delete')) ?></button></form>
        <?php endif ?>
    </div>
</div>
<?php endif ?>
