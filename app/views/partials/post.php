<?php
/** One post card. $p = hydrated post (Posts::hydrate). $fresh = just created (animates in). */
$me = Auth::id();
$a = $p['author'];
$pid = (int) $p['id'];
$privIcon = ['public' => 'globe', 'followers' => 'users', 'only_me' => 'lock'][$p['privacy']] ?? 'globe';
$body = (string) $p['body'];
$fmt = $p['format'];
$short = $fmt === 'text' && $body !== '' && mb_strlen($body) <= 90 && !$p['images'] && !$p['shared'] && substr_count($body, "\n") < 2;
$pg = $p['page'];
$anon = (bool) $p['anonymous'];
// who may see the real author of an anonymous post: the author, site staff, and the admins / moderators of the group it is in
$seeReal = $anon && $me && ((int) $p['user_id'] === $me || Auth::isMod() || ($p['group_id'] && Groups::isManager((int) $p['group_id'], $me)));
$isGroupManager = $me && $p['group_id'] && Groups::isManager((int) $p['group_id'], $me);
$gallery = function (array $images, int $postId) {
    if (!$images) {
        return '';
    }
    $n = count($images);
    $cls = 'n' . min($n, 4);
    $h = '<div class="zl-gallery ' . $cls . '">';
    foreach (array_slice($images, 0, 4) as $i => $im) {
        $more = ($i === 3 && $n > 4) ? ' class="more"' : '';
        $h .= '<a href="' . e(upload_url($im['path'])) . '" data-lightbox="g' . $postId . '"' . $more . ' aria-label="' . e(t('post.open_photo')) . '">'
            . '<img src="' . e(upload_url($n === 1 ? $im['path'] : $im['thumb'])) . '" alt="" loading="lazy" decoding="async"'
            . ($n === 1 && $im['width'] ? ' width="' . (int) $im['width'] . '" height="' . (int) $im['height'] . '"' : '') . '>'
            . ($more ? '<span>+' . ($n - 4) . '</span>' : '') . '</a>';
    }
    return $h . '</div>';
};
// avatar, name and link of whoever the post shows as its author
$who = function (array $post, string $size) {
    if ($post['page']) {
        return [Pages::avatar($post['page'], $size), e($post['page']['name']) . ($post['page']['verified'] ? ' ' . icon('verified', 'zl-verified') : ''), url('pages/' . $post['page']['slug'])];
    }
    if ($post['anonymous']) {
        return ['<span class="zl-av zl-av-' . $size . ' zl-av-anon" aria-hidden="true">' . icon('mask') . '</span>', e(t('anon.name')), null];
    }
    $au = $post['author'];
    return [avatar_html($au, $size), e(display_name($au)) . ($au['verified'] ? ' ' . icon('verified', 'zl-verified') : '') . Pro::badge($au), url('u/' . $au['username'])];
};
[$avHtml, $nameHtml, $link] = $who($p, 'md');
?>
<article class="zl-card zl-post<?= $p['status'] !== 'published' ? ' is-' . e($p['status']) : '' ?><?= !empty($fresh) ? ' is-fresh' : '' ?>" id="post-<?= $pid ?>" data-post="<?= $pid ?>">
    <?php if (!empty($pinnedLabel)): ?><div class="zl-post-flag"><?= icon('thumbtack', 'zl-i-sm') ?><?= e(t('group.pinned_post')) ?></div><?php endif ?>
    <?php if ($p['status'] === 'hidden'): ?><div class="zl-post-flag is-hidden"><?= icon('eye-off', 'zl-i-sm') ?><?= e(t('post.is_hidden')) ?></div><?php endif ?>
    <?php if ($p['status'] === 'pending'): ?><div class="zl-post-flag is-pending"><?= icon('clock', 'zl-i-sm') ?><?= e(t('post.is_pending')) ?></div><?php endif ?>
    <header class="zl-post-head">
        <?php if ($link): ?><a href="<?= e($link) ?>" aria-hidden="true" tabindex="-1"><?= $avHtml ?></a><?php else: ?><?= $avHtml ?><?php endif ?>
        <div class="zl-post-who">
            <?php if ($link): ?><a class="zl-strong" href="<?= e($link) ?>"><?= $nameHtml ?></a><?php else: ?><span class="zl-strong"><?= $nameHtml ?></span><?php endif ?>
            <?php if ($seeReal): ?> <a class="zl-anon-real" href="<?= e(url('u/' . $a['username'])) ?>" title="<?= e(t('anon.real_hint')) ?>"><?= icon('eye', 'zl-i-sm') ?><?= (int) $p['user_id'] === $me ? e(t('anon.you')) : '@' . e($a['username']) ?></a><?php endif ?>
            <?php if ($p['group']): ?> <span class="zl-faint">›</span> <a class="zl-group-chip" href="<?= e(url('g/' . $p['group']['id'])) ?>"><?= e($p['group']['name']) ?></a><?php endif ?>
            <?php if ($p['shared'] || $p['shared_missing']): ?> <span class="zl-faint"><?= e(t('post.shared_a_post')) ?></span><?php endif ?>
            <div class="zl-post-meta">
                <a href="<?= e(url('p/' . $pid)) ?>" title="<?= e(date('Y-m-d H:i', (int) $p['created_at'])) ?>"><time datetime="<?= e(date('c', (int) $p['created_at'])) ?>"><?= e(ago((int) $p['created_at'])) ?></time></a>
                <?php if (!$p['group_id'] && !$pg): ?><span aria-hidden="true">·</span><span title="<?= e(t('privacy.' . $p['privacy'])) ?>"><?= icon($privIcon) ?><span class="sr-only"><?= e(t('privacy.' . $p['privacy'])) ?></span></span><?php endif ?>
                <?php if ($pg && $me && Pages::isAdmin((int) $pg['id'], $me) && (int) $p['user_id'] !== $me): ?><span aria-hidden="true">·</span><span><?= e(t('pages.posted_by', ['name' => display_name($a)])) ?></span><?php endif ?>
                <?php if ($p['edited_at']): ?><span aria-hidden="true">·</span><span><?= e(t('post.edited')) ?></span><?php endif ?>
            </div>
        </div>
        <?php if ($me): ?>
        <div class="zl-menu">
            <button type="button" class="zl-icon-btn" data-menu aria-haspopup="true" aria-expanded="false" aria-label="<?= e(t('post.options')) ?>"><?= icon('dots') ?></button>
            <div class="zl-menu-list" hidden>
                <button type="button" data-copy="<?= e(full_url('p/' . $pid)) ?>"><?= icon('link', 'zl-i-sm') ?><?= e(t('post.copy_link')) ?></button>
                <?php if (Posts::canEdit($p, $me)): ?><button type="button" data-edit-post="<?= $pid ?>"><?= icon('edit', 'zl-i-sm') ?><?= e(t('post.edit')) ?></button><?php endif ?>
                <?php if ($isGroupManager && $p['status'] === 'published'): $isPinned = (int) ($p['group']['pinned_post_id'] ?? 0) === $pid; ?>
                    <button type="button" data-post-action="<?= e(url('p/' . $pid . '/' . ($isPinned ? 'unpin' : 'pin'))) ?>"><?= icon('thumbtack', 'zl-i-sm') ?><?= e($isPinned ? t('group.unpin') : t('group.pin')) ?></button>
                <?php endif ?>
                <?php if ($isGroupManager && $p['status'] === 'pending'): ?>
                    <form method="post" action="<?= e(url('p/' . $pid . '/approve')) ?>"><?= csrf_field() ?><button><?= icon('check', 'zl-i-sm') ?><?= e(t('group.approve_post')) ?></button></form>
                    <form method="post" action="<?= e(url('p/' . $pid . '/reject')) ?>"><?= csrf_field() ?><button class="is-danger"><?= icon('x', 'zl-i-sm') ?><?= e(t('group.reject_post')) ?></button></form>
                <?php endif ?>
                <?php if (Auth::isMod() && $p['status'] !== 'pending'): ?>
                    <button type="button" data-post-action="<?= e(url('p/' . $pid . '/' . ($p['status'] === 'hidden' ? 'unhide' : 'hide'))) ?>" data-toggle-hidden><?= icon($p['status'] === 'hidden' ? 'eye' : 'eye-off', 'zl-i-sm') ?><?= e($p['status'] === 'hidden' ? t('post.unhide') : t('post.hide')) ?></button>
                <?php endif ?>
                <?php if (Posts::canDelete($p, $me)): ?><button type="button" class="is-danger" data-delete-post="<?= $pid ?>" data-url="<?= e(url('p/' . $pid . '/delete')) ?>"><?= icon('trash', 'zl-i-sm') ?><?= e(t('post.delete')) ?></button><?php endif ?>
                <?php if ((int) $p['user_id'] !== $me): ?><button type="button" data-report="post:<?= $pid ?>"><?= icon('flag', 'zl-i-sm') ?><?= e(t('report.post')) ?></button><?php endif ?>
                <?= Ext::html('post_menu', $p) ?>
            </div>
        </div>
        <?php endif ?>
    </header>

    <?php if ($body !== ''): ?><div class="zl-post-body<?= $short ? ' is-short' : '' ?><?= $fmt === 'html' ? ' zl-rich' : '' ?>" data-body><?= Text::body($body, $fmt) ?></div><?php endif ?>
    <?php if ($me && Posts::canEdit($p, $me)): ?>
    <form class="zl-edit-box" data-edit-form="<?= $pid ?>" action="<?= e(url('p/' . $pid . '/edit')) ?>" method="post" hidden>
        <?= csrf_field() ?>
        <?php View::partial('editor', ['name' => 'body', 'id' => 'edit-' . $pid, 'value' => $body, 'format' => $fmt, 'label' => t('post.edit'), 'max' => Posts::maxBody()]); ?>
        <div class="zl-row">
            <?php if (!$p['group_id'] && !$pg && !$anon): ?>
            <label class="sr-only" for="ep-<?= $pid ?>"><?= e(t('privacy.who')) ?></label>
            <select id="ep-<?= $pid ?>" name="privacy" class="zl-input zl-privacy-sel"><?php foreach (Posts::PRIVACY as $pv): ?><option value="<?= e($pv) ?>" <?= $p['privacy'] === $pv ? 'selected' : '' ?>><?= e(t('privacy.' . $pv)) ?></option><?php endforeach ?></select>
            <?php endif ?>
            <span style="flex:1"></span>
            <button type="button" class="zl-btn zl-btn-soft zl-btn-sm" data-edit-cancel><?= e(t('common.cancel')) ?></button>
            <button class="zl-btn zl-btn-main zl-btn-sm"><?= e(t('common.save')) ?></button>
        </div>
    </form>
    <?php endif ?>

    <?= $gallery($p['images'], $pid) ?>

    <?php if ($p['shared']): $s = $p['shared']; [$sAv, $sName, $sLink] = $who($s, 'xs'); ?>
    <div class="zl-shared">
        <div class="zl-row">
            <?= $sAv ?>
            <?php if ($sLink): ?><a class="zl-strong" href="<?= e($sLink) ?>"><?= $sName ?></a><?php else: ?><span class="zl-strong"><?= $sName ?></span><?php endif ?>
            <a class="zl-faint" href="<?= e(url('p/' . $s['id'])) ?>"><?= e(ago((int) $s['created_at'])) ?></a>
        </div>
        <?php if ((string) $s['body'] !== ''): ?><div class="zl-post-body<?= $s['format'] === 'html' ? ' zl-rich' : '' ?>"><?= Text::body($s['body'], $s['format']) ?></div><?php endif ?>
        <?= $gallery($s['images'], (int) $s['id']) ?>
    </div>
    <?php elseif ($p['shared_missing']): ?>
    <div class="zl-shared zl-faint"><?= e(t('post.shared_unavailable')) ?></div>
    <?php endif ?>

    <?= Ext::html('post_after_body', $p) ?>

    <div class="zl-post-stats">
        <span data-like-count><?= $p['reactions_count'] ? e(tc('post.n_likes', (int) $p['reactions_count'])) : '' ?></span>
        <span>
            <a href="<?= e(url('p/' . $pid)) ?>" data-comment-count><?= $p['comments_count'] ? e(tc('post.n_comments', (int) $p['comments_count'])) : '' ?></a>
            <?php if ($p['shares_count']): ?> · <?= e(tc('post.n_shares', (int) $p['shares_count'])) ?><?php endif ?>
        </span>
    </div>

    <?php if ($me && $p['status'] === 'published'): ?>
    <div class="zl-actions">
        <button type="button" class="zl-act<?= $p['liked'] ? ' is-liked' : '' ?>" data-like="<?= e(url('p/' . $pid . '/like')) ?>" aria-pressed="<?= $p['liked'] ? 'true' : 'false' ?>"><?= icon('heart') ?><span><?= e(t('post.like')) ?></span></button>
        <?php if (setting('comments_enabled', '1') === '1'): ?>
        <a class="zl-act" href="<?= e(url('p/' . $pid)) ?>#comments" data-toggle-comments="<?= $pid ?>"><?= icon('comment') ?><span><?= e(t('post.comment')) ?></span></a>
        <?php endif ?>
        <?php $shareable = !$p['group_id'] && ($p['shared'] ? $p['shared']['privacy'] === 'public' : $p['privacy'] === 'public'); if ($shareable): ?>
        <button type="button" class="zl-act" data-share="<?= e(url('p/' . $pid . '/share')) ?>"><?= icon('repeat') ?><span><?= e(t('post.share')) ?></span></button>
        <?php endif ?>
        <button type="button" class="zl-act zl-act-save<?= $p['is_saved'] ? ' is-saved' : '' ?>" data-save="<?= e(url('p/' . $pid . '/save')) ?>" aria-pressed="<?= $p['is_saved'] ? 'true' : 'false' ?>" title="<?= e($p['is_saved'] ? t('post.saved_label') : t('post.save')) ?>"><?= icon('bookmark') ?><span data-save-label><?= e($p['is_saved'] ? t('post.saved_label') : t('post.save')) ?></span></button>
    </div>
    <?php if (setting('comments_enabled', '1') === '1'): ?>
    <div class="zl-comments" data-comments="<?= $pid ?>" hidden>
        <div class="zl-comments-list" data-list data-src="<?= e(url('p/' . $pid . '/comments')) ?>"></div>
        <?php View::partial('comment-form', ['p' => $p]); ?>
    </div>
    <?php endif ?>
    <?php endif ?>
</article>
