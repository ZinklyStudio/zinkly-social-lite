<?php $me = Auth::id(); $isMe = $me === (int) $u['id']; ?>
<div class="zl-col">
    <section class="zl-card">
        <div class="zl-cover" id="cover-box"><?php if ($u['cover']): ?><img src="<?= e(upload_url($u['cover'])) ?>" alt=""><?php endif ?>
            <?php if ($isMe) { View::partial('photo-edit', ['action' => url('me/photo'), 'type' => 'cover', 'has' => (bool) $u['cover'], 'target' => '#cover-box']); } ?>
        </div>
        <div class="zl-profile-head">
            <div class="zl-profile-top">
                <span class="zl-av-wrap<?= Users::online($u) ? ' zl-online' : '' ?>" id="avatar-box"><?= avatar_html($u, 'xl') ?>
                    <?php if ($isMe) { View::partial('photo-edit', ['action' => url('me/photo'), 'type' => 'avatar', 'has' => (bool) $u['avatar'], 'target' => '#avatar-box']); } ?>
                </span>
                <div class="zl-row">
                    <?php if ($isMe): ?>
                        <a class="zl-btn zl-btn-soft zl-btn-sm" href="<?= e(url('settings')) ?>"><?= icon('edit', 'zl-i-sm') ?><?= e(t('profile.edit')) ?></a>
                    <?php elseif ($me && !$blockedMe): ?>
                        <?php if (!$blocked) { View::partial('follow-button', ['u' => $u, 'status' => $follow, 'small' => true]); } ?>
                        <?php if (!$blocked && setting('messages_enabled', '1') === '1' && Users::canMessage($u, $me)): ?><a class="zl-btn zl-btn-soft zl-btn-sm" href="<?= e(url('messages/new/' . $u['username'])) ?>"><?= icon('chat', 'zl-i-sm') ?><?= e(t('profile.message')) ?></a><?php endif ?>
                        <div class="zl-menu">
                            <button type="button" class="zl-icon-btn" data-menu aria-haspopup="true" aria-expanded="false" aria-label="<?= e(t('post.options')) ?>"><?= icon('dots') ?></button>
                            <div class="zl-menu-list" hidden>
                                <button type="button" data-copy="<?= e(full_url('u/' . $u['username'])) ?>"><?= icon('link', 'zl-i-sm') ?><?= e(t('post.copy_link')) ?></button>
                                <form method="post" action="<?= e(url('u/' . $u['username'] . '/' . ($blocked ? 'unblock' : 'block'))) ?>" <?= $blocked ? '' : 'data-confirm="' . e(t('profile.block_confirm', ['name' => display_name($u)])) . '"' ?>><?= csrf_field() ?><button type="submit" class="<?= $blocked ? '' : 'is-danger' ?>"><?= icon('ban', 'zl-i-sm') ?><?= e($blocked ? t('profile.unblock') : t('profile.block')) ?></button></form>
                                <button type="button" data-report="user:<?= (int) $u['id'] ?>"><?= icon('flag', 'zl-i-sm') ?><?= e(t('report.user')) ?></button>
                            </div>
                        </div>
                    <?php endif ?>
                </div>
            </div>
            <h1 class="zl-profile-name"><?= e(display_name($u)) ?><?php if ($u['verified']): ?> <?= icon('verified', 'zl-verified') ?><?php endif ?><?= Pro::badge($u) ?></h1>
            <p class="zl-faint">@<?= e($u['username']) ?><?php if ($u['private_account']): ?> · <?= icon('lock', 'zl-i-sm') ?><?php endif ?><?php if ($u['banned']): ?> · <span class="zl-pill is-banned"><?= e(t('admin.banned')) ?></span><?php endif ?></p>
            <?php if ($u['bio'] !== ''): ?><p class="zl-post-body" style="margin:10px 0 0"><?= Text::render($u['bio']) ?></p><?php endif ?>
            <div class="zl-profile-facts">
                <?php if ($u['location'] !== ''): ?><span><?= icon('pin') ?><?= e($u['location']) ?></span><?php endif ?>
                <?php if ($u['website'] !== ''): ?><a class="zl-link" href="<?= e($u['website']) ?>" rel="nofollow noopener ugc" target="_blank"><?= icon('link') ?><?= e(str_limit(preg_replace('~^https?://(www\.)?~', '', $u['website']), 40)) ?></a><?php endif ?>
                <span><?= icon('calendar') ?><?= e(t('profile.joined', ['date' => Lang::date((int) $u['created_at'])])) ?></span>
            </div>
            <div class="zl-profile-stats">
                <span><b><?= e(short_number((int) $u['posts_count'])) ?></b> <?= e(tc('profile.posts_word', (int) $u['posts_count'])) ?></span>
                <a href="<?= e(url('u/' . $u['username'] . '/followers')) ?>"><b data-followers><?= e(short_number((int) $u['followers_count'])) ?></b> <?= e(t('profile.followers')) ?></a>
                <a href="<?= e(url('u/' . $u['username'] . '/following')) ?>"><b><?= e(short_number((int) $u['following_count'])) ?></b> <?= e(t('profile.following')) ?></a>
            </div>
        </div>
        <nav class="zl-tabs" style="border-top:1px solid var(--border)" aria-label="<?= e(t('profile.tabs')) ?>">
            <a href="<?= e(url('u/' . $u['username'])) ?>" class="<?= $tab === 'posts' ? 'is-on' : '' ?>"><?= e(t('profile.tab_posts')) ?></a>
            <a href="<?= e(url('u/' . $u['username'], ['tab' => 'media'])) ?>" class="<?= $tab === 'media' ? 'is-on' : '' ?>"><?= e(t('profile.tab_media')) ?></a>
        </nav>
    </section>

    <?php if ($requests): ?>
    <section class="zl-card">
        <h2 class="zl-h2" style="padding:14px 16px 4px"><?= e(t('profile.follow_requests')) ?></h2>
        <div class="zl-people">
            <?php foreach ($requests as $r): ?>
            <div class="zl-person">
                <a href="<?= e(url('u/' . $r['username'])) ?>"><?= avatar_html($r, 'sm') ?></a>
                <a class="zl-person-info" href="<?= e(url('u/' . $r['username'])) ?>"><b><?= e(display_name($r)) ?></b><small>@<?= e($r['username']) ?></small></a>
                <form method="post" action="<?= e(url('u/' . $r['username'] . '/accept')) ?>"><?= csrf_field() ?><button class="zl-btn zl-btn-main zl-btn-sm"><?= e(t('profile.accept')) ?></button></form>
                <form method="post" action="<?= e(url('u/' . $r['username'] . '/decline')) ?>"><?= csrf_field() ?><button class="zl-btn zl-btn-soft zl-btn-sm"><?= e(t('profile.decline')) ?></button></form>
            </div>
            <?php endforeach ?>
        </div>
    </section>
    <?php endif ?>

    <?php if ($isMe && $tab === 'posts') { View::partial('composer', ['idSuffix' => 'profile']); } ?>
</div>
<div style="height:14px"></div>
<?php if ($blockedMe): ?>
    <div class="zl-col"><div class="zl-card zl-empty"><?= icon('ban') ?><b><?= e(t('profile.unavailable')) ?></b></div></div>
<?php elseif (!$canSee): ?>
    <div class="zl-col"><div class="zl-card zl-empty"><?= icon('lock') ?><b><?= e(t('profile.private')) ?></b><span><?= e(t('profile.private_hint')) ?></span></div></div>
<?php else: ?>
    <?php View::partial('feed', ['posts' => $posts, 'scope' => $scope, 'emptyIcon' => $tab === 'media' ? 'image' : 'file', 'emptyTitle' => t('profile.no_posts')]); ?>
<?php endif ?>
