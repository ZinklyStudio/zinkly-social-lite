<?php
/** Admin › Members › one member: account, password, security (IP addresses and devices) and content. */
$mid = (int) $m['id'];
$base = 'admin/users/' . $mid;
$tabs = ['account' => ['user', t('member.tab_account')], 'security' => ['shield', t('member.tab_security')],
    'posts' => ['file', t('member.tab_posts') . ' · ' . $counts['posts']], 'media' => ['image', t('member.tab_media') . ' · ' . ($counts['images'] + $counts['stories'])]];
$back = fn (string $tab) => '<input type="hidden" name="_back" value="' . e($base . '?tab=' . $tab) . '">';
?>
<div class="zl-settings zl-admin">
    <?php View::partial('admin-nav', ['anav' => $anav]); ?>
    <div class="zl-stack">
        <div class="zl-card zl-pad zl-member-head">
            <a class="zl-icon-btn" href="<?= e(url('admin/users')) ?>" aria-label="<?= e(t('common.back')) ?>"><?= icon('arrow-left') ?></a>
            <?= avatar_html($m, 'lg') ?>
            <div style="min-width:0;flex:1">
                <h1 class="zl-h1"><?= e(display_name($m)) ?><?php if ($m['verified']): ?> <?= icon('verified', 'zl-verified') ?><?php endif ?><?= Pro::badge($m) ?></h1>
                <p class="zl-faint">@<?= e($m['username']) ?> · <?= e($m['email']) ?> · #<?= $mid ?></p>
                <div class="zl-row" style="gap:6px;flex-wrap:wrap;margin-top:6px">
                    <span class="zl-pill<?= $m['role'] !== 'user' ? ' is-admin' : '' ?>"><?= e(t('admin.role_' . $m['role'])) ?></span>
                    <?php if ($m['banned']): ?><span class="zl-pill is-banned"><?= e(t('admin.banned')) ?></span><?php endif ?>
                    <span class="zl-pill"><?= e(t('admin.joined')) ?> <?= e(Lang::date((int) $m['created_at'])) ?></span>
                    <span class="zl-pill<?= Users::online($m) ? ' is-ok' : '' ?>"><?= Users::online($m) ? e(t('msg.online')) : e(t('msg.last_seen', ['time' => ago((int) $m['last_seen'])])) ?></span>
                    <?php if ($lastLogin): ?><span class="zl-pill"><?= icon('globe', 'zl-i-sm') ?> <?= e($lastLogin['ip']) ?> · <?= e($lastLogin['device']) ?></span><?php endif ?>
                    <?php if ($counts['failed']): ?><span class="zl-pill is-warn"><?= e(tc('member.n_failed', $counts['failed'])) ?></span><?php endif ?>
                </div>
            </div>
            <a class="zl-btn zl-btn-soft zl-btn-sm" href="<?= e(url('u/' . $m['username'])) ?>"><?= icon('eye', 'zl-i-sm') ?><?= e(t('member.view_profile')) ?></a>
        </div>

        <nav class="zl-chips" aria-label="<?= e(t('member.sections')) ?>">
            <?php foreach ($tabs as $k => [$ic, $label]): ?><a class="zl-chip<?= $tab === $k ? ' is-on' : '' ?>" href="<?= e(url($base, ['tab' => $k])) ?>" <?= $tab === $k ? 'aria-current="page"' : '' ?>><?= icon($ic, 'zl-i-sm') ?><?= e($label) ?></a><?php endforeach ?>
        </nav>

<?php if ($tab === 'account'): ?>
        <div class="zl-admin-grid">
            <form class="zl-card zl-pad zl-stack" method="post" action="<?= e(url($base . '/account')) ?>" autocomplete="off">
                <?= csrf_field() ?>
                <h2 class="zl-h2"><?= icon('user', 'zl-i-sm') ?> <?= e(t('member.account')) ?></h2>
                <div class="zl-grid2">
                    <label class="zl-field"><span><?= e(t('auth.username')) ?></span><input class="zl-input" name="username" value="<?= e($m['username']) ?>" maxlength="32" pattern="[A-Za-z0-9_]{3,32}" required spellcheck="false"><small class="zl-hint"><?= e(t('member.username_hint')) ?></small></label>
                    <label class="zl-field"><span><?= e(t('member.display_name')) ?></span><input class="zl-input" name="name" value="<?= e($m['name']) ?>" maxlength="80"></label>
                </div>
                <label class="zl-field"><span><?= e(t('auth.email')) ?></span><input class="zl-input" type="email" name="email" value="<?= e($m['email']) ?>" maxlength="190" required spellcheck="false"></label>
                <label class="zl-field"><span><?= e(t('member.bio')) ?></span><textarea class="zl-input" name="bio" rows="2" maxlength="300"><?= e($m['bio']) ?></textarea></label>
                <label class="zl-check"><span><?= e(t('admin.verify')) ?><small><?= e(t('member.verified_hint')) ?></small></span><span class="zl-switch"><input type="checkbox" name="verified" value="1" <?= $m['verified'] ? 'checked' : '' ?>><span></span></span></label>
                <button class="zl-btn zl-btn-main"><?= icon('check', 'zl-i-sm') ?><?= e(t('common.save')) ?></button>
            </form>
            <div class="zl-stack">
                <form class="zl-card zl-pad zl-stack" method="post" action="<?= e(url($base . '/password')) ?>" autocomplete="off">
                    <?= csrf_field() ?>
                    <h2 class="zl-h2"><?= icon('key', 'zl-i-sm') ?> <?= e(t('member.set_password')) ?></h2>
                    <label class="zl-field"><span><?= e(t('auth.new_password')) ?></span><input class="zl-input" type="text" name="password" minlength="8" autocomplete="new-password" spellcheck="false" required><small class="zl-hint"><?= e(t('member.password_hint')) ?></small></label>
                    <label class="zl-row" style="font-size:14px"><input type="checkbox" name="signout" value="1" checked> <?= e(t('member.and_signout')) ?></label>
                    <label class="zl-row" style="font-size:14px"><input type="checkbox" name="notify" value="1"> <?= e(t('member.and_notify')) ?></label>
                    <button class="zl-btn zl-btn-main"><?= icon('key', 'zl-i-sm') ?><?= e(t('member.set_password_btn')) ?></button>
                </form>
                <form class="zl-card zl-pad zl-stack" method="post" action="<?= e(url($base . '/send-reset')) ?>">
                    <?= csrf_field() ?>
                    <h2 class="zl-h2"><?= icon('send', 'zl-i-sm') ?> <?= e(t('member.send_reset')) ?></h2>
                    <p class="zl-faint"><?= e(t('member.send_reset_hint', ['email' => $m['email']])) ?></p>
                    <button class="zl-btn zl-btn-soft"><?= icon('send', 'zl-i-sm') ?><?= e(t('member.send_reset_btn')) ?></button>
                    <?php if (setting('mail_method', 'php') === 'off'): ?><p class="zl-note"><?= e(t('member.mail_off')) ?></p><?php endif ?>
                </form>
            </div>
        </div>

<?php elseif ($tab === 'security'): ?>
        <div class="zl-admin-grid">
            <section class="zl-card zl-pad zl-stack">
                <h2 class="zl-h2"><?= icon('monitor', 'zl-i-sm') ?> <?= e(t('member.devices')) ?></h2>
                <?php foreach ($devices as $d): ?>
                    <div class="zl-between zl-trend-row"><span><b class="zl-strong"><?= e($d['device']) ?></b><br><small class="zl-faint"><?= e($d['ip']) ?> · <?= e(tc('member.n_signins', (int) $d['n'])) ?></small></span><small class="zl-faint"><?= e(ago((int) $d['last_at'])) ?></small></div>
                <?php endforeach ?>
                <?php if (!$devices): ?><p class="zl-faint"><?= e(t('member.no_history')) ?></p><?php endif ?>
                <form method="post" action="<?= e(url($base . '/signout')) ?>" data-confirm="<?= e(t('member.signout_confirm')) ?>"><?= csrf_field() ?><button class="zl-btn zl-btn-danger zl-btn-sm"><?= icon('logout', 'zl-i-sm') ?><?= e(t('member.signout_all')) ?></button></form>
                <small class="zl-hint"><?= e(t('member.signout_hint')) ?></small>
            </section>
            <section class="zl-card zl-pad zl-stack">
                <h2 class="zl-h2"><?= icon('users', 'zl-i-sm') ?> <?= e(t('member.same_ip')) ?></h2>
                <p class="zl-faint"><?= e(t('member.same_ip_hint')) ?></p>
                <?php foreach ($sameIp as $o): ?>
                    <a class="zl-between zl-trend-row" href="<?= e(url('admin/users/' . $o['id'], ['tab' => 'security'])) ?>"><span class="zl-row"><?= avatar_html($o, 'sm') ?><b class="zl-strong">@<?= e($o['username']) ?></b></span><small class="zl-faint"><?= e($o['ip']) ?></small></a>
                <?php endforeach ?>
                <?php if (!$sameIp): ?><p class="zl-faint"><?= e(t('member.same_ip_none')) ?></p><?php endif ?>
            </section>
            <section class="zl-card zl-span-all">
                <div class="zl-pad"><h2 class="zl-h2"><?= icon('clock', 'zl-i-sm') ?> <?= e(t('member.history')) ?></h2><p class="zl-faint"><?= e(t('member.history_hint')) ?></p></div>
                <div class="zl-table-wrap"><table class="zl-table">
                    <thead><tr><th><?= e(t('member.when')) ?></th><th><?= e(t('member.ip')) ?></th><th><?= e(t('member.device')) ?></th><th><?= e(t('member.how')) ?></th><th><?= e(t('admin.status')) ?></th></tr></thead>
                    <tbody><?php foreach ($history as $h): ?><tr>
                        <td><?= e(Lang::date((int) $h['created_at'])) ?> <?= date('H:i', (int) $h['created_at']) ?></td>
                        <td><code><?= e($h['ip']) ?></code></td>
                        <td title="<?= e($h['agent']) ?>"><?= e($h['device']) ?></td>
                        <td><?= e(t('member.m_' . $h['method'])) ?></td>
                        <td><span class="zl-pill <?= $h['ok'] ? 'is-ok' : 'is-bad' ?>"><?= e($h['ok'] ? t('member.ok') : t('member.failed')) ?></span></td>
                    </tr><?php endforeach ?>
                    <?php if (!$history): ?><tr><td colspan="5"><div class="zl-empty"><?= icon('shield') ?><b><?= e(t('member.no_history')) ?></b></div></td></tr><?php endif ?></tbody>
                </table></div>
            </section>
        </div>

<?php elseif ($tab === 'posts'): ?>
        <section class="zl-card">
            <div class="zl-table-wrap"><table class="zl-table">
                <thead><tr><th><?= e(t('member.post')) ?></th><th><?= e(t('admin.status')) ?></th><th><span class="sr-only"><?= e(t('admin.actions')) ?></span></th></tr></thead>
                <tbody><?php foreach ($posts as $p): $th = $thumbs[(int) $p['id']] ?? []; ?><tr>
                    <td><div class="zl-row" style="align-items:flex-start">
                        <?php if ($th): ?><a class="zl-member-thumb" href="<?= e(url('admin/posts/' . $p['id'] . '/edit')) ?>"><img src="<?= e(upload_url($th[0])) ?>" alt="" loading="lazy"><?php if (count($th) > 1): ?><b>+<?= count($th) - 1 ?></b><?php endif ?></a><?php endif ?>
                        <div style="min-width:0"><a class="zl-strong" href="<?= e(url('admin/posts/' . $p['id'] . '/edit')) ?>"><?= e(str_limit(Text::plain((string) $p['body'], $p['format'] ?? 'text'), 120) ?: '—') ?></a>
                        <br><small class="zl-faint"><?= e(Lang::date((int) $p['created_at'])) ?> · <?= e(t('privacy.' . $p['privacy'])) ?><?php if ($p['anonymous']): ?> · <?= e(t('member.anonymous')) ?><?php endif ?></small></div></div></td>
                    <td><span class="zl-pill<?= $p['status'] === 'published' ? ' is-ok' : ($p['status'] === 'hidden' ? ' is-bad' : ' is-warn') ?>"><?= e(t('member.s_' . $p['status'])) ?></span></td>
                    <td><div class="zl-row" style="gap:4px;justify-content:flex-end">
                        <a class="zl-icon-btn" href="<?= e(url('p/' . $p['id'])) ?>" aria-label="<?= e(t('admin.view')) ?>" title="<?= e(t('admin.view')) ?>"><?= icon('eye') ?></a>
                        <a class="zl-icon-btn" href="<?= e(url('admin/posts/' . $p['id'] . '/edit')) ?>" aria-label="<?= e(t('post.edit')) ?>" title="<?= e(t('post.edit')) ?>"><?= icon('edit') ?></a>
                        <form method="post" action="<?= e(url('admin/posts/' . $p['id'] . '/' . ($p['status'] === 'hidden' ? 'unhide' : 'hide'))) ?>"><?= csrf_field() ?><?= $back('posts') ?><button class="zl-icon-btn" aria-label="<?= e($p['status'] === 'hidden' ? t('member.unhide') : t('member.hide')) ?>" title="<?= e($p['status'] === 'hidden' ? t('member.unhide') : t('member.hide')) ?>"><?= icon($p['status'] === 'hidden' ? 'eye' : 'eye-off') ?></button></form>
                        <form method="post" action="<?= e(url('admin/posts/' . $p['id'] . '/delete')) ?>" data-confirm="<?= e(t('js.delete_post')) ?>"><?= csrf_field() ?><?= $back('posts') ?><button class="zl-icon-btn" aria-label="<?= e(t('post.delete')) ?>" title="<?= e(t('post.delete')) ?>"><?= icon('trash') ?></button></form>
                    </div></td>
                </tr><?php endforeach ?>
                <?php if (!$posts): ?><tr><td colspan="3"><div class="zl-empty"><?= icon('file') ?><b><?= e(t('member.no_posts')) ?></b></div></td></tr><?php endif ?></tbody>
            </table></div>
            <?php if ($page > 1 || count($posts) === 20): ?><div class="zl-pager"><?php if ($page > 1): ?><a class="zl-btn zl-btn-soft zl-btn-sm" href="<?= e(url($base, ['tab' => 'posts', 'page' => $page - 1])) ?>"><?= e(t('common.previous')) ?></a><?php endif ?><?php if (count($posts) === 20): ?><a class="zl-btn zl-btn-soft zl-btn-sm" href="<?= e(url($base, ['tab' => 'posts', 'page' => $page + 1])) ?>"><?= e(t('common.next')) ?></a><?php endif ?></div><?php endif ?>
        </section>

<?php else: ?>
        <section class="zl-card zl-pad zl-stack">
            <h2 class="zl-h2"><?= icon('camera', 'zl-i-sm') ?> <?= e(t('member.stories')) ?></h2>
            <div class="zl-media-grid">
                <?php foreach ($stories as $s): ?>
                    <div class="zl-media-item">
                        <div class="zl-media-box zl-media-story<?= $s['type'] === 'text' ? ' zl-bg-' . e($s['bg'] ?: 'violet') : '' ?>">
                            <?php if ($s['type'] === 'image'): ?><img src="<?= e(upload_url($s['path'])) ?>" alt="" loading="lazy">
                            <?php elseif ($s['type'] === 'video'): ?><video src="<?= e(upload_url($s['path'])) ?>" muted controls preload="metadata"></video>
                            <?php else: ?><span><?= e(str_limit((string) $s['text_body'], 80)) ?></span><?php endif ?>
                        </div>
                        <small class="zl-faint"><?= e(t('member.t_' . $s['type'])) ?> · <?= e(ago((int) $s['created_at'])) ?> · <?= e(tc('js.n_views', (int) $s['views_count'])) ?></small>
                        <div class="zl-row" style="gap:6px;flex-wrap:wrap">
                            <?php if ($s['type'] !== 'text'): ?><form method="post" action="<?= e(url('admin/stories/' . $s['id'] . '/replace')) ?>" enctype="multipart/form-data" class="zl-replace"><?= csrf_field() ?><?= $back('media') ?><label class="zl-btn zl-btn-soft zl-btn-sm"><?= icon('upload', 'zl-i-sm') ?><?= e(t('member.replace')) ?><input class="sr-only" type="file" name="media" accept="image/*,video/mp4,video/webm,video/quicktime" data-autosubmit></label></form><?php endif ?>
                            <form method="post" action="<?= e(url('admin/stories/' . $s['id'] . '/delete')) ?>" data-confirm="<?= e(t('member.delete_story')) ?>"><?= csrf_field() ?><?= $back('media') ?><button class="zl-btn zl-btn-soft zl-btn-sm"><?= icon('trash', 'zl-i-sm') ?><?= e(t('post.delete')) ?></button></form>
                        </div>
                    </div>
                <?php endforeach ?>
                <?php if (!$stories): ?><p class="zl-faint"><?= e(t('member.no_stories')) ?></p><?php endif ?>
            </div>
        </section>
        <section class="zl-card zl-pad zl-stack">
            <h2 class="zl-h2"><?= icon('image', 'zl-i-sm') ?> <?= e(t('member.photos')) ?></h2>
            <div class="zl-media-grid">
                <?php foreach ($avatarCover as $which => $path): ?>
                    <div class="zl-media-item">
                        <div class="zl-media-box"><img src="<?= e(upload_url($path)) ?>" alt="" loading="lazy"></div>
                        <small class="zl-faint"><?= e(t('member.' . $which)) ?></small>
                        <form method="post" action="<?= e(url($base . '/clear-photo')) ?>" data-confirm="<?= e(t('member.remove_photo_confirm')) ?>"><?= csrf_field() ?><input type="hidden" name="which" value="<?= e($which) ?>"><button class="zl-btn zl-btn-soft zl-btn-sm"><?= icon('trash', 'zl-i-sm') ?><?= e(t('trend.remove')) ?></button></form>
                    </div>
                <?php endforeach ?>
                <?php foreach ($images as $im): ?>
                    <div class="zl-media-item">
                        <a class="zl-media-box" href="<?= e(url('admin/posts/' . $im['post_id'] . '/edit')) ?>"><img src="<?= e(upload_url($im['thumb'])) ?>" alt="" loading="lazy"></a>
                        <small class="zl-faint"><?= e(t('member.in_post', ['id' => $im['post_id']])) ?><?php if ($im['status'] !== 'published'): ?> · <?= e(t('member.s_' . $im['status'])) ?><?php endif ?></small>
                        <div class="zl-row" style="gap:6px;flex-wrap:wrap">
                            <form method="post" action="<?= e(url('admin/images/' . $im['id'] . '/replace')) ?>" enctype="multipart/form-data"><?= csrf_field() ?><?= $back('media') ?><label class="zl-btn zl-btn-soft zl-btn-sm"><?= icon('upload', 'zl-i-sm') ?><?= e(t('member.replace')) ?><input class="sr-only" type="file" name="image" accept="image/jpeg,image/png,image/webp,image/gif" data-autosubmit></label></form>
                            <form method="post" action="<?= e(url('admin/images/' . $im['id'] . '/remove')) ?>" data-confirm="<?= e(t('member.remove_photo_confirm')) ?>"><?= csrf_field() ?><?= $back('media') ?><button class="zl-btn zl-btn-soft zl-btn-sm"><?= icon('trash', 'zl-i-sm') ?><?= e(t('trend.remove')) ?></button></form>
                        </div>
                    </div>
                <?php endforeach ?>
                <?php if (!$images && !$avatarCover): ?><p class="zl-faint"><?= e(t('member.no_photos')) ?></p><?php endif ?>
            </div>
        </section>
<?php endif ?>
    </div>
</div>
