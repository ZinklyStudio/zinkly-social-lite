<?php
$q = fn (array $over) => url('admin/posts', array_filter(array_merge($f, ['page' => null], $over), fn ($v) => $v !== '' && $v !== null));
$statusPill = ['published' => '', 'hidden' => ' is-banned', 'pending' => ' is-warn'];
?>
<div class="zl-settings zl-admin">
    <?php View::partial('admin-nav', ['anav' => $anav]); ?>
    <div class="zl-stack">
        <div class="zl-card zl-pad">
            <div class="zl-between" style="flex-wrap:wrap">
                <div><h1 class="zl-h1"><?= e(t('admin.posts')) ?> <span class="zl-badge"><?= (int) $total ?></span></h1><p class="zl-faint"><?= e(t('admin.posts_hint2')) ?></p></div>
            </div>
            <div class="zl-mini-stats">
                <a href="<?= e($q(['status' => '', 'type' => ''])) ?>" class="<?= $f['status'] === '' && $f['type'] === '' ? 'is-on' : '' ?>"><b><?= e(short_number($counts['all'])) ?></b><small><?= e(t('admin.all')) ?></small></a>
                <a href="<?= e($q(['status' => 'published', 'type' => ''])) ?>" class="<?= $f['status'] === 'published' ? 'is-on' : '' ?>"><b><?= e(short_number($counts['published'])) ?></b><small><?= e(t('admin.st_published')) ?></small></a>
                <a href="<?= e($q(['status' => 'hidden', 'type' => ''])) ?>" class="<?= $f['status'] === 'hidden' ? 'is-on' : '' ?>"><b><?= e(short_number($counts['hidden'])) ?></b><small><?= e(t('admin.st_hidden')) ?></small></a>
                <a href="<?= e($q(['status' => 'pending', 'type' => ''])) ?>" class="<?= $f['status'] === 'pending' ? 'is-on' : '' ?>"><b><?= e(short_number($counts['pending'])) ?></b><small><?= e(t('admin.st_pending')) ?></small></a>
                <a href="<?= e($q(['type' => 'anonymous', 'status' => ''])) ?>" class="<?= $f['type'] === 'anonymous' ? 'is-on' : '' ?>"><b><?= e(short_number($counts['anonymous'])) ?></b><small><?= e(t('admin.t_anonymous')) ?></small></a>
            </div>
            <form class="zl-filters" method="get" action="<?= e(url('admin/posts')) ?>">
                <label class="sr-only" for="pf-q"><?= e(t('nav.search')) ?></label><input id="pf-q" class="zl-input" name="q" value="<?= e($f['q']) ?>" placeholder="<?= e(t('admin.search_posts')) ?>">
                <label class="sr-only" for="pf-a"><?= e(t('admin.author')) ?></label><input id="pf-a" class="zl-input" name="author" value="<?= e($f['author']) ?>" placeholder="@<?= e(t('admin.author')) ?>">
                <label class="sr-only" for="pf-s"><?= e(t('admin.status')) ?></label>
                <select id="pf-s" class="zl-input" name="status"><option value=""><?= e(t('admin.any_status')) ?></option><?php foreach (Posts::STATUSES as $st): ?><option value="<?= $st ?>" <?= $f['status'] === $st ? 'selected' : '' ?>><?= e(t('admin.st_' . $st)) ?></option><?php endforeach ?></select>
                <label class="sr-only" for="pf-t"><?= e(t('admin.type')) ?></label>
                <select id="pf-t" class="zl-input" name="type"><option value=""><?= e(t('admin.any_type')) ?></option><?php foreach (['profile', 'anonymous', 'group', 'page', 'rich', 'photos', 'shared', 'reported'] as $tp): ?><option value="<?= $tp ?>" <?= $f['type'] === $tp ? 'selected' : '' ?>><?= e(t('admin.t_' . $tp)) ?></option><?php endforeach ?></select>
                <button class="zl-btn zl-btn-soft"><?= icon('search', 'zl-i-sm') ?><?= e(t('admin.filter_btn')) ?></button>
                <?php if (array_filter($f)): ?><a class="zl-btn zl-btn-ghost" href="<?= e(url('admin/posts')) ?>"><?= e(t('admin.clear')) ?></a><?php endif ?>
            </form>
        </div>

        <form class="zl-card zl-table-wrap" method="post" action="<?= e(url('admin/posts/bulk')) ?>" data-bulk-form>
            <?= csrf_field() ?>
            <div class="zl-bulk-bar" data-bulk-bar>
                <label class="zl-row" style="gap:8px;font-size:14px"><input type="checkbox" data-check-all aria-label="<?= e(t('admin.select_all')) ?>"> <span data-bulk-count><?= e(t('admin.select_all')) ?></span></label>
                <span style="flex:1"></span>
                <button class="zl-btn zl-btn-soft zl-btn-sm" name="action" value="hide" disabled data-bulk-btn><?= icon('eye-off', 'zl-i-sm') ?><?= e(t('post.hide')) ?></button>
                <button class="zl-btn zl-btn-soft zl-btn-sm" name="action" value="unhide" disabled data-bulk-btn><?= icon('eye', 'zl-i-sm') ?><?= e(t('post.unhide')) ?></button>
                <button class="zl-btn zl-btn-danger zl-btn-sm" name="action" value="delete" disabled data-bulk-btn data-bulk-confirm="<?= e(t('admin.bulk_delete_confirm')) ?>"><?= icon('trash', 'zl-i-sm') ?><?= e(t('post.delete')) ?></button>
            </div>
            <table class="zl-table zl-posts-table">
                <thead><tr><th style="width:36px"><span class="sr-only"><?= e(t('admin.select')) ?></span></th><th><?= e(t('admin.post')) ?></th><th><?= e(t('admin.author')) ?></th><th><?= e(t('admin.where')) ?></th><th><?= e(t('admin.stats')) ?></th><th><?= e(t('admin.status')) ?></th><th><span class="sr-only"><?= e(t('admin.actions')) ?></span></th></tr></thead>
                <tbody>
                <?php foreach ($posts as $p): $pid = (int) $p['id']; $a = $p['author']; $plain = Text::plain((string) $p['body'], $p['format']); ?>
                    <tr class="<?= $p['status'] !== 'published' ? 'is-' . e($p['status']) : '' ?>">
                        <td><input type="checkbox" name="ids[]" value="<?= $pid ?>" data-check aria-label="<?= e(t('admin.select')) ?> #<?= $pid ?>"></td>
                        <td class="zl-post-cell">
                            <a class="zl-strong" href="<?= e(url('p/' . $pid)) ?>">#<?= $pid ?></a>
                            <span class="zl-faint"><?= e(ago((int) $p['created_at'])) ?></span>
                            <?php if ($p['format'] === 'html'): ?><span class="zl-pill"><?= e(t('admin.t_rich')) ?></span><?php endif ?>
                            <?php if ($p['images']): ?><span class="zl-pill"><?= icon('image', 'zl-i-sm') ?> <?= count($p['images']) ?></span><?php endif ?>
                            <?php if ($p['shared_post_id']): ?><span class="zl-pill"><?= icon('repeat', 'zl-i-sm') ?></span><?php endif ?>
                            <?php if (!empty($reported[$pid])): ?><span class="zl-pill is-banned"><?= icon('flag', 'zl-i-sm') ?> <?= (int) $reported[$pid] ?></span><?php endif ?>
                            <p class="zl-excerpt"><?= $plain !== '' ? e(str_limit($plain, 160)) : '<span class="zl-faint">' . e(t('admin.no_text')) . '</span>' ?></p>
                            <?php if ($p['images']): ?><div class="zl-thumbs"><?php foreach (array_slice($p['images'], 0, 4) as $im): ?><img src="<?= e(upload_url($im['thumb'])) ?>" alt="" loading="lazy"><?php endforeach ?></div><?php endif ?>
                        </td>
                        <td>
                            <div class="zl-row" style="gap:8px">
                                <?= avatar_html($a, 'sm') ?>
                                <div style="min-width:0;line-height:1.3">
                                    <?php if ($p['anonymous']): ?><span class="zl-pill is-anon"><?= icon('mask', 'zl-i-sm') ?> <?= e(t('anon.name')) ?></span><br><?php endif ?>
                                    <a class="zl-strong" href="<?= e(url('u/' . $a['username'])) ?>"><?= e(display_name($a)) ?></a><br>
                                    <small class="zl-faint">@<?= e($a['username']) ?><?php if ($p['anonymous']): ?> · <?= e(t('admin.real_author')) ?><?php endif ?></small>
                                </div>
                            </div>
                        </td>
                        <td>
                            <?php if ($p['page']): ?><a class="zl-group-chip" href="<?= e(url('pages/' . $p['page']['slug'])) ?>"><?= icon('page', 'zl-i-sm') ?><?= e($p['page']['name']) ?></a>
                            <?php elseif ($p['group']): ?><a class="zl-group-chip" href="<?= e(url('g/' . $p['group']['id'])) ?>"><?= icon('users', 'zl-i-sm') ?><?= e($p['group']['name']) ?></a>
                            <?php else: ?><span class="zl-faint"><?= icon(['public' => 'globe', 'followers' => 'users', 'only_me' => 'lock'][$p['privacy']] ?? 'globe', 'zl-i-sm') ?> <?= e(t('privacy.' . $p['privacy'])) ?></span><?php endif ?>
                        </td>
                        <td class="zl-faint zl-nowrap"><?= icon('heart', 'zl-i-sm') ?> <?= (int) $p['reactions_count'] ?> &nbsp;<?= icon('comment', 'zl-i-sm') ?> <?= (int) $p['comments_count'] ?></td>
                        <td><span class="zl-pill<?= $statusPill[$p['status']] ?? '' ?>"><?= e(t('admin.st_' . $p['status'])) ?></span></td>
                        <td>
                            <div class="zl-menu">
                                <button type="button" class="zl-icon-btn" data-menu aria-haspopup="true" aria-expanded="false" aria-label="<?= e(t('admin.actions')) ?>"><?= icon('dots') ?></button>
                                <div class="zl-menu-list" hidden>
                                    <a href="<?= e(url('p/' . $pid)) ?>"><?= icon('eye', 'zl-i-sm') ?><?= e(t('admin.view')) ?></a>
                                    <?php if (Auth::isAdmin()): ?><a href="<?= e(url('admin/posts/' . $pid . '/edit')) ?>"><?= icon('edit', 'zl-i-sm') ?><?= e(t('member.edit_post')) ?></a><?php endif ?>
                                    <a href="<?= e(url('admin/posts', ['author' => $a['username']])) ?>"><?= icon('user', 'zl-i-sm') ?><?= e(t('admin.more_by', ['name' => $a['username']])) ?></a>
                                    <?php if ($p['status'] !== 'pending'): ?><button type="button" data-post-action="<?= e(url('p/' . $pid . '/' . ($p['status'] === 'hidden' ? 'unhide' : 'hide'))) ?>" data-reload><?= icon($p['status'] === 'hidden' ? 'eye' : 'eye-off', 'zl-i-sm') ?><?= e($p['status'] === 'hidden' ? t('post.unhide') : t('post.hide')) ?></button><?php endif ?>
                                    <button type="button" class="is-danger" data-delete-post="<?= $pid ?>" data-url="<?= e(url('p/' . $pid . '/delete')) ?>" data-reload><?= icon('trash', 'zl-i-sm') ?><?= e(t('post.delete')) ?></button>
                                </div>
                            </div>
                        </td>
                    </tr>
                <?php endforeach ?>
                <?php if (!$posts): ?><tr><td colspan="7"><div class="zl-empty"><?= icon('file') ?><b><?= e(t('admin.no_posts_found')) ?></b></div></td></tr><?php endif ?>
                </tbody>
            </table>
            <?php if ($pages > 1): ?>
            <div class="zl-pager">
                <?php if ($page > 1): ?><a class="zl-btn zl-btn-sm zl-btn-soft" href="<?= e($q(['page' => $page - 1])) ?>"><?= icon('arrow-left', 'zl-i-sm') ?></a><?php endif ?>
                <?php for ($i = max(1, $page - 3); $i <= min($pages, $page + 3); $i++): ?><a class="zl-btn zl-btn-sm <?= $i === $page ? 'zl-btn-main' : 'zl-btn-soft' ?>" href="<?= e($q(['page' => $i])) ?>"><?= $i ?></a><?php endfor ?>
                <?php if ($page < $pages): ?><a class="zl-btn zl-btn-sm zl-btn-soft" href="<?= e($q(['page' => $page + 1])) ?>"><?= icon('arrow-right', 'zl-i-sm') ?></a><?php endif ?>
            </div>
            <?php endif ?>
        </form>
    </div>
</div>
