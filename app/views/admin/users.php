<div class="zl-settings zl-admin">
    <?php View::partial('admin-nav', ['anav' => $anav]); ?>
    <div class="zl-stack">
        <div class="zl-card zl-pad"><h1 class="zl-h1"><?= e(t('admin.users')) ?> <span class="zl-badge"><?= (int) $total ?></span></h1>
            <form class="zl-row" style="margin-top:12px;flex-wrap:wrap" method="get" action="<?= e(url('admin/users')) ?>">
                <label class="sr-only" for="uq"><?= e(t('nav.search')) ?></label><input id="uq" class="zl-input" style="flex:1;min-width:180px" name="q" value="<?= e($q) ?>" placeholder="<?= e(t('admin.search_users')) ?>">
                <label class="sr-only" for="uf"><?= e(t('admin.filter')) ?></label><select id="uf" class="zl-input" style="width:auto" name="filter"><option value=""><?= e(t('admin.all')) ?></option><option value="staff" <?= $filter === 'staff' ? 'selected' : '' ?>><?= e(t('admin.staff')) ?></option><option value="banned" <?= $filter === 'banned' ? 'selected' : '' ?>><?= e(t('admin.banned')) ?></option></select>
                <button class="zl-btn zl-btn-soft"><?= e(t('nav.search')) ?></button>
            </form>
        </div>
        <div class="zl-card zl-table-wrap">
            <table class="zl-table">
                <thead><tr><th><?= e(t('admin.member')) ?></th><th><?= e(t('admin.role')) ?></th><th><?= e(t('admin.joined')) ?></th><th><span class="sr-only"><?= e(t('admin.actions')) ?></span></th></tr></thead>
                <tbody>
                <?php foreach ($users as $x): ?>
                    <tr>
                        <td><div class="zl-row"><?= avatar_html($x, 'sm') ?><div style="min-width:0"><a class="zl-strong" href="<?= e(url('admin/users/' . $x['id'])) ?>"><?= e(display_name($x)) ?></a><?php if ($x['verified']): ?> <?= icon('verified', 'zl-verified') ?><?php endif ?><br><small class="zl-faint">@<?= e($x['username']) ?> · <?= e($x['email']) ?></small></div></div></td>
                        <td><span class="zl-pill<?= $x['role'] !== 'user' ? ' is-admin' : '' ?>"><?= e(t('admin.role_' . $x['role'])) ?></span><?php if ($x['banned']): ?> <span class="zl-pill is-banned"><?= e(t('admin.banned')) ?></span><?php endif ?></td>
                        <td class="zl-faint"><?= e(Lang::date((int) $x['created_at'])) ?></td>
                        <td>
                            <?php if ((int) $x['id'] !== Auth::id()): ?>
                            <div class="zl-menu">
                                <button type="button" class="zl-icon-btn" data-menu aria-haspopup="true" aria-expanded="false" aria-label="<?= e(t('admin.actions')) ?>"><?= icon('dots') ?></button>
                                <div class="zl-menu-list" hidden>
                                    <a href="<?= e(url('admin/users/' . $x['id'])) ?>"><?= icon('settings', 'zl-i-sm') ?><?= e(t('member.manage')) ?></a>
                                    <a href="<?= e(url('admin/users/' . $x['id'], ['tab' => 'security'])) ?>"><?= icon('shield', 'zl-i-sm') ?><?= e(t('member.tab_security')) ?></a>
                                    <form method="post" action="<?= e(url('admin/users/' . $x['id'] . '/verify')) ?>"><?= csrf_field() ?><button><?= icon('verified', 'zl-i-sm') ?><?= e($x['verified'] ? t('admin.unverify') : t('admin.verify')) ?></button></form>
                                    <?php foreach (['user', 'moderator', 'admin'] as $r): if ($r === $x['role']) { continue; } ?>
                                        <form method="post" action="<?= e(url('admin/users/' . $x['id'] . '/role')) ?>"><?= csrf_field() ?><input type="hidden" name="role" value="<?= $r ?>"><button><?= icon('shield', 'zl-i-sm') ?><?= e(t('admin.make_' . $r)) ?></button></form>
                                    <?php endforeach ?>
                                    <form method="post" action="<?= e(url('admin/users/' . $x['id'] . '/' . ($x['banned'] ? 'unban' : 'ban'))) ?>"><?= csrf_field() ?><button class="<?= $x['banned'] ? '' : 'is-danger' ?>"><?= icon('ban', 'zl-i-sm') ?><?= e($x['banned'] ? t('admin.unban') : t('admin.ban')) ?></button></form>
                                    <form method="post" action="<?= e(url('admin/users/' . $x['id'] . '/delete')) ?>" data-confirm="<?= e(t('admin.delete_user_confirm', ['name' => $x['username']])) ?>"><?= csrf_field() ?><button class="is-danger"><?= icon('trash', 'zl-i-sm') ?><?= e(t('admin.delete_user')) ?></button></form>
                                </div>
                            </div>
                            <?php else: ?><a class="zl-icon-btn" href="<?= e(url('admin/users/' . $x['id'])) ?>" aria-label="<?= e(t('member.manage')) ?>" title="<?= e(t('member.manage')) ?>"><?= icon('settings') ?></a>
                            <?php endif ?>
                        </td>
                    </tr>
                <?php endforeach ?>
                </tbody>
            </table>
            <?php if ($pages > 1): ?><div class="zl-pager"><?php for ($i = 1; $i <= $pages; $i++): ?><a class="zl-btn zl-btn-sm <?= $i === $page ? 'zl-btn-main' : 'zl-btn-soft' ?>" href="<?= e(url('admin/users', ['q' => $q, 'filter' => $filter, 'page' => $i])) ?>"><?= $i ?></a><?php endfor ?></div><?php endif ?>
        </div>
    </div>
</div>
