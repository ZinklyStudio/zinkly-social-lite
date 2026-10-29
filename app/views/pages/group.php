<?php
$me = Auth::id();
$isAdmin = $role === 'admin' || Auth::isMod();
$gid = (int) $g['id'];
$groupAvatar = function (array $g, string $size) {
    return !empty($g['avatar']) ? '<span class="zl-av zl-av-' . $size . ' zl-av-group"><img src="' . e(upload_url($g['avatar'])) . '" alt=""></span>'
        : '<span class="zl-av zl-av-' . $size . ' zl-av-group" aria-hidden="true">' . e(initials($g['name'])) . '</span>';
};
$tabUrl = fn (string $tb) => url('g/' . $gid, $tb === 'posts' ? [] : ['tab' => $tb]);
?>
<div class="zl-col zl-col-wide">
    <section class="zl-card">
        <div class="zl-group-cover zl-cover-lg" id="g-cover"><?php if ($g['cover']): ?><img src="<?= e(upload_url($g['cover'])) ?>" alt=""><?php endif ?>
            <?php if ($isAdmin) { View::partial('photo-edit', ['action' => url('g/' . $gid . '/photo'), 'type' => 'cover', 'has' => (bool) $g['cover'], 'target' => '#g-cover']); } ?>
        </div>
        <div class="zl-pad zl-entity-head">
            <span class="zl-av-wrap zl-entity-av" id="g-avatar"><?= $groupAvatar($g, 'xl') ?>
                <?php if ($isAdmin) { View::partial('photo-edit', ['action' => url('g/' . $gid . '/photo'), 'type' => 'avatar', 'has' => !empty($g['avatar']), 'target' => '#g-avatar']); } ?>
            </span>
            <div class="zl-between" style="align-items:flex-start;flex-wrap:wrap">
                <div><h1 class="zl-h1"><?= e($g['name']) ?></h1>
                    <p class="zl-faint zl-meta-line"><?= icon($g['privacy'] === 'private' ? 'lock' : 'globe', 'zl-i-sm') ?> <?= e(t('group.' . $g['privacy'])) ?>
                        <?php if ($g['visibility'] === 'hidden'): ?> · <?= icon('eye-off', 'zl-i-sm') ?> <?= e(t('group.hidden')) ?><?php endif ?>
                        · <?= e(tc('group.n_members', (int) $g['members_count'])) ?>
                        <?php if ($g['category'] !== ''): ?> · <a class="zl-chip-sm" href="<?= e(url('groups', ['cat' => $g['category']])) ?>"><?= e(t('group.cat_' . $g['category'])) ?></a><?php endif ?></p></div>
                <div class="zl-row" style="flex-wrap:wrap">
                    <?php if (!$role): ?>
                        <form method="post" action="<?= e(url('g/' . $gid . '/join')) ?>"><?= csrf_field() ?><button class="zl-btn zl-btn-main zl-btn-sm"><?= icon('plus', 'zl-i-sm') ?><?= e($g['privacy'] === 'private' ? t('group.ask_join') : t('group.join')) ?></button></form>
                    <?php elseif ($role === 'pending'): ?>
                        <form method="post" action="<?= e(url('g/' . $gid . '/leave')) ?>"><?= csrf_field() ?><button class="zl-btn zl-btn-soft zl-btn-sm"><?= e(t('group.cancel_request')) ?></button></form>
                    <?php elseif ((int) $g['owner_id'] !== $me): ?>
                        <form method="post" action="<?= e(url('g/' . $gid . '/leave')) ?>" data-confirm="<?= e(t('group.leave_confirm')) ?>"><?= csrf_field() ?><button class="zl-btn zl-btn-soft zl-btn-sm"><?= icon('check', 'zl-i-sm') ?><?= e(t('group.joined_btn')) ?></button></form>
                    <?php endif ?>
                    <button type="button" class="zl-btn zl-btn-soft zl-btn-sm" data-copy="<?= e(full_url('g/' . $gid)) ?>"><?= icon('share', 'zl-i-sm') ?><?= e(t('group.invite')) ?></button>
                    <?php if ($isAdmin): ?><button type="button" class="zl-btn zl-btn-soft zl-btn-sm" data-open="group-edit"><?= icon('settings', 'zl-i-sm') ?><?= e(t('group.manage')) ?></button><?php endif ?>
                    <?php if ($me && $role !== 'admin'): ?><button type="button" class="zl-icon-btn" data-report="group:<?= $gid ?>" aria-label="<?= e(t('report.group')) ?>" title="<?= e(t('report.group')) ?>"><?= icon('flag') ?></button><?php endif ?>
                </div>
            </div>
            <?php if ($g['description'] !== ''): ?><p class="zl-post-body"><?= Text::render($g['description']) ?></p><?php endif ?>
            <?php if ($members && $tab !== 'members'): ?><div class="zl-avatars" style="margin-top:12px"><?php foreach (array_slice($members, 0, 12) as $m): ?><a href="<?= e(url('u/' . $m['user']['username'])) ?>" title="<?= e(display_name($m['user'])) ?>"><?= avatar_html($m['user'], 'sm') ?></a><?php endforeach ?></div><?php endif ?>
        </div>
        <nav class="zl-tabs" style="border-top:1px solid var(--border)" aria-label="<?= e(t('group.sections')) ?>">
            <a href="<?= e($tabUrl('posts')) ?>" class="<?= $tab === 'posts' ? 'is-on' : '' ?>"><?= e(t('group.tab_posts')) ?></a>
            <a href="<?= e($tabUrl('members')) ?>" class="<?= $tab === 'members' ? 'is-on' : '' ?>"><?= e(t('group.tab_members')) ?> <span class="zl-badge"><?= (int) $g['members_count'] ?></span></a>
            <a href="<?= e($tabUrl('about')) ?>" class="<?= $tab === 'about' ? 'is-on' : '' ?>"><?= e(t('group.tab_about')) ?></a>
            <?php if ($manager): ?><a href="<?= e($tabUrl('review')) ?>" class="<?= $tab === 'review' ? 'is-on' : '' ?>"><?= e(t('group.tab_review')) ?><?php if ($pendingPosts + count($pending)): ?> <span class="zl-count"><?= $pendingPosts + count($pending) ?></span><?php endif ?></a><?php endif ?>
        </nav>
    </section>

    <?php if ($tab === 'review'): ?>
        <section class="zl-card">
            <h2 class="zl-h2" style="padding:14px 16px 4px"><?= e(t('group.requests')) ?></h2>
            <?php if (!$pending): ?><p class="zl-faint" style="padding:4px 16px 14px"><?= e(t('group.no_requests')) ?></p><?php endif ?>
            <div class="zl-people"><?php foreach ($pending as $m): $pu = $m['user']; ?>
                <div class="zl-person"><?= avatar_html($pu, 'sm') ?><a class="zl-person-info" href="<?= e(url('u/' . $pu['username'])) ?>"><b><?= e(display_name($pu)) ?></b><small>@<?= e($pu['username']) ?></small></a>
                    <form method="post" action="<?= e(url('g/' . $gid . '/approve')) ?>"><?= csrf_field() ?><input type="hidden" name="tab" value="review"><input type="hidden" name="user_id" value="<?= (int) $pu['id'] ?>"><button class="zl-btn zl-btn-main zl-btn-sm"><?= e(t('profile.accept')) ?></button></form>
                    <form method="post" action="<?= e(url('g/' . $gid . '/remove')) ?>"><?= csrf_field() ?><input type="hidden" name="tab" value="review"><input type="hidden" name="user_id" value="<?= (int) $pu['id'] ?>"><button class="zl-btn zl-btn-soft zl-btn-sm"><?= e(t('profile.decline')) ?></button></form>
                </div>
            <?php endforeach ?></div>
        </section>
        <div class="zl-card zl-pad"><h2 class="zl-h2"><?= e(t('group.pending_posts')) ?></h2><p class="zl-faint"><?= e(t('group.pending_posts_hint')) ?></p></div>
    <?php elseif ($tab === 'members'): ?>
        <section class="zl-card">
            <div class="zl-people"><?php foreach ($members as $m): $mu = $m['user']; $isOwner = (int) $g['owner_id'] === (int) $mu['id']; ?>
                <div class="zl-person">
                    <a href="<?= e(url('u/' . $mu['username'])) ?>"><?= avatar_html($mu, 'md') ?></a>
                    <a class="zl-person-info" href="<?= e(url('u/' . $mu['username'])) ?>"><b><?= e(display_name($mu)) ?><?php if ($mu['verified']): ?> <?= icon('verified', 'zl-verified') ?><?php endif ?></b><small>@<?= e($mu['username']) ?></small></a>
                    <span class="zl-pill<?= $m['role'] !== 'member' ? ' is-admin' : '' ?>"><?= e($isOwner ? t('group.owner') : t('group.role_' . $m['role'])) ?></span>
                    <?php if (($isAdmin || ($manager && $m['role'] === 'member')) && !$isOwner && (int) $mu['id'] !== $me): ?>
                    <div class="zl-menu">
                        <button type="button" class="zl-icon-btn" data-menu aria-haspopup="true" aria-expanded="false" aria-label="<?= e(t('admin.actions')) ?>"><?= icon('dots') ?></button>
                        <div class="zl-menu-list" hidden>
                            <?php if ($isAdmin): foreach (Groups::ROLES as $r): if ($r === $m['role']) { continue; } ?>
                                <form method="post" action="<?= e(url('g/' . $gid . '/set-role')) ?>"><?= csrf_field() ?><input type="hidden" name="user_id" value="<?= (int) $mu['id'] ?>"><input type="hidden" name="role" value="<?= $r ?>"><button><?= icon('shield', 'zl-i-sm') ?><?= e(t('group.make_' . $r)) ?></button></form>
                            <?php endforeach; endif ?>
                            <form method="post" action="<?= e(url('g/' . $gid . '/remove')) ?>" data-confirm="<?= e(t('group.remove_confirm', ['name' => display_name($mu)])) ?>"><?= csrf_field() ?><input type="hidden" name="tab" value="members"><input type="hidden" name="user_id" value="<?= (int) $mu['id'] ?>"><button class="is-danger"><?= icon('ban', 'zl-i-sm') ?><?= e(t('group.remove_member')) ?></button></form>
                        </div>
                    </div>
                    <?php endif ?>
                </div>
            <?php endforeach ?></div>
        </section>
    <?php elseif ($tab === 'about'): ?>
        <section class="zl-card zl-pad zl-stack">
            <h2 class="zl-h2"><?= e(t('group.tab_about')) ?></h2>
            <?php if ($g['description'] !== ''): ?><p class="zl-post-body" style="margin:0"><?= Text::render($g['description']) ?></p><?php endif ?>
            <ul class="zl-facts">
                <li><?= icon($g['privacy'] === 'private' ? 'lock' : 'globe') ?><div><b><?= e(t('group.' . $g['privacy'])) ?></b><small><?= e(t('group.' . $g['privacy'] . '_hint')) ?></small></div></li>
                <li><?= icon($g['visibility'] === 'hidden' ? 'eye-off' : 'eye') ?><div><b><?= e(t('group.vis_' . $g['visibility'])) ?></b><small><?= e(t('group.vis_' . $g['visibility'] . '_hint')) ?></small></div></li>
                <li><?= icon('edit') ?><div><b><?= e(t('group.perm_' . $g['post_permission'])) ?></b><small><?= e($g['post_approval'] ? t('group.approval_on') : t('group.approval_off')) ?></small></div></li>
                <?php if ($g['allow_anonymous']): ?><li><?= icon('mask') ?><div><b><?= e(t('group.anon_on')) ?></b><small><?= e(t('group.anon_on_hint')) ?></small></div></li><?php endif ?>
                <li><?= icon('calendar') ?><div><b><?= e(t('group.created', ['date' => Lang::date((int) $g['created_at'])])) ?></b></div></li>
            </ul>
            <?php if (trim((string) $g['rules']) !== ''): ?>
                <h2 class="zl-h2"><?= e(t('group.rules')) ?></h2>
                <ol class="zl-rules"><?php foreach (array_filter(array_map('trim', explode("\n", (string) $g['rules']))) as $rule): ?><li><?= e($rule) ?></li><?php endforeach ?></ol>
            <?php endif ?>
        </section>
    <?php else: ?>
        <?php if ($role === 'admin' || $role === 'moderator' || $role === 'member'): ?>
            <?php if (Groups::canPost($g, $me)) { View::partial('composer', ['groupId' => $gid, 'group' => $g, 'idSuffix' => 'group']); } else { ?>
                <div class="zl-card zl-pad zl-faint"><?= icon('lock', 'zl-i-sm') ?> <?= e(t('group.admins_only')) ?></div>
            <?php } ?>
        <?php endif ?>
        <?php if ($pinned): View::partial('post', ['p' => $pinned, 'pinnedLabel' => true]); endif ?>
    <?php endif ?>
</div>
<?php if (in_array($tab, ['posts', 'review'], true)): ?>
<div style="height:14px"></div>
<?php if (!$can): ?>
    <div class="zl-col zl-col-wide"><div class="zl-card zl-empty"><?= icon('lock') ?><b><?= e(t('group.private_title')) ?></b><span><?= e(t('group.private_text')) ?></span></div></div>
<?php else: ?>
    <?php View::partial('feed', ['posts' => $posts, 'scope' => $scope, 'wide' => true, 'emptyIcon' => $tab === 'review' ? 'check' : 'users', 'emptyTitle' => $tab === 'review' ? t('group.no_pending') : t('group.no_posts'), 'emptyText' => '']); ?>
<?php endif ?>
<?php endif ?>

<?php if ($isAdmin): ?>
<div class="zl-modal" id="group-edit" hidden role="dialog" aria-modal="true" aria-labelledby="ge-title">
    <div class="zl-sheet zl-sheet-wide">
        <div class="zl-sheet-head"><b id="ge-title"><?= e(t('group.manage')) ?></b><button type="button" class="zl-icon-btn" data-close aria-label="<?= e(t('common.close')) ?>"><?= icon('x') ?></button></div>
        <form class="zl-sheet-body" method="post" action="<?= e(url('g/' . $gid . '/edit')) ?>" enctype="multipart/form-data">
            <?= csrf_field() ?>
            <div class="zl-grid2">
                <label class="zl-field"><span><?= e(t('group.name')) ?></span><input class="zl-input" name="name" value="<?= e($g['name']) ?>" maxlength="80" required></label>
                <label class="zl-field"><span><?= e(t('group.category')) ?></span><select class="zl-input" name="category"><option value=""><?= e(t('group.cat_none')) ?></option><?php foreach (Groups::CATEGORIES as $c): ?><option value="<?= $c ?>" <?= $g['category'] === $c ? 'selected' : '' ?>><?= e(t('group.cat_' . $c)) ?></option><?php endforeach ?></select></label>
            </div>
            <label class="zl-field"><span><?= e(t('group.description')) ?></span><textarea class="zl-input" name="description" maxlength="500"><?= e($g['description']) ?></textarea></label>
            <label class="zl-field"><span><?= e(t('group.rules')) ?></span><textarea class="zl-input" name="rules" maxlength="3000" placeholder="<?= e(t('group.rules_ph')) ?>"><?= e((string) $g['rules']) ?></textarea></label>
            <div class="zl-grid2">
                <label class="zl-field"><span><?= e(t('group.photo')) ?></span><input class="zl-input" type="file" name="avatar" accept="image/jpeg,image/png,image/webp"></label>
                <label class="zl-field"><span><?= e(t('group.cover')) ?></span><input class="zl-input" type="file" name="cover" accept="image/jpeg,image/png,image/webp"></label>
            </div>
            <h3 class="zl-h3"><?= e(t('group.privacy')) ?></h3>
            <div class="zl-grid2">
                <label class="zl-check"><input type="radio" name="privacy" value="public" <?= $g['privacy'] === 'public' ? 'checked' : '' ?>><span><?= e(t('group.public')) ?><small><?= e(t('group.public_hint')) ?></small></span></label>
                <label class="zl-check"><input type="radio" name="privacy" value="private" <?= $g['privacy'] === 'private' ? 'checked' : '' ?>><span><?= e(t('group.private')) ?><small><?= e(t('group.private_hint')) ?></small></span></label>
            </div>
            <h3 class="zl-h3"><?= e(t('group.options')) ?></h3>
            <label class="zl-check"><span><?= e(t('group.vis_hidden')) ?><small><?= e(t('group.vis_hidden_hint')) ?></small></span><span class="zl-switch"><input type="checkbox" name="visibility" value="hidden" <?= $g['visibility'] === 'hidden' ? 'checked' : '' ?>><span></span></span></label>
            <label class="zl-check"><span><?= e(t('group.perm_admins')) ?><small><?= e(t('group.perm_admins_hint')) ?></small></span><span class="zl-switch"><input type="checkbox" name="post_permission" value="admins" <?= $g['post_permission'] === 'admins' ? 'checked' : '' ?>><span></span></span></label>
            <label class="zl-check"><span><?= e(t('group.approval')) ?><small><?= e(t('group.approval_hint_admin')) ?></small></span><span class="zl-switch"><input type="checkbox" name="post_approval" value="1" <?= $g['post_approval'] ? 'checked' : '' ?>><span></span></span></label>
            <?php if (setting('anonymous_posts', '1') === '1'): ?>
            <label class="zl-check"><span><?= e(t('group.anon_on')) ?><small><?= e(t('group.anon_on_hint')) ?></small></span><span class="zl-switch"><input type="checkbox" name="allow_anonymous" value="1" <?= $g['allow_anonymous'] ? 'checked' : '' ?>><span></span></span></label>
            <?php endif ?>
            <button class="zl-btn zl-btn-main zl-btn-block"><?= e(t('common.save')) ?></button>
        </form>
        <?php if ((int) $g['owner_id'] === $me || Auth::isAdmin()): ?>
        <form class="zl-sheet-body" style="border-top:1px solid var(--border)" method="post" action="<?= e(url('g/' . $gid . '/delete')) ?>" data-confirm="<?= e(t('group.delete_confirm')) ?>"><?= csrf_field() ?><button class="zl-btn zl-btn-danger zl-btn-block"><?= icon('trash', 'zl-i-sm') ?><?= e(t('group.delete')) ?></button></form>
        <?php endif ?>
    </div>
</div>
<?php endif ?>
