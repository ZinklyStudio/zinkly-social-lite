<div class="zl-settings zl-admin">
    <?php View::partial('admin-nav', ['anav' => $anav]); ?>
    <div class="zl-stack">
        <div class="zl-card zl-pad">
            <div class="zl-between" style="flex-wrap:wrap"><div><h1 class="zl-h1"><?= e(t('admin.pages_social')) ?> <span class="zl-badge"><?= count($rows) ?></span></h1><p class="zl-faint"><?= e(t('admin.pages_hint')) ?></p></div>
                <a class="zl-btn zl-btn-soft zl-btn-sm" href="<?= e(url('pages')) ?>"><?= icon('eye', 'zl-i-sm') ?><?= e(t('admin.view')) ?></a></div>
            <form class="zl-filters" method="get" action="<?= e(url('admin/pages')) ?>"><label class="sr-only" for="apq"><?= e(t('nav.search')) ?></label><input id="apq" class="zl-input" name="q" value="<?= e($q) ?>" placeholder="<?= e(t('pages.search')) ?>"><button class="zl-btn zl-btn-soft"><?= icon('search', 'zl-i-sm') ?><?= e(t('nav.search')) ?></button></form>
        </div>
        <div class="zl-card zl-table-wrap"><table class="zl-table">
            <thead><tr><th><?= e(t('pages.name')) ?></th><th><?= e(t('pages.category')) ?></th><th><?= e(t('admin.owner')) ?></th><th><?= e(t('pages.followers')) ?></th><th><?= e(t('admin.s_posts')) ?></th><th><span class="sr-only"><?= e(t('admin.actions')) ?></span></th></tr></thead>
            <tbody><?php foreach ($rows as $pg): $ow = $owners[(int) $pg['owner_id']] ?? null; ?><tr>
                <td><div class="zl-row" style="gap:8px"><?= Pages::avatar($pg, 'sm') ?><div><a class="zl-strong" href="<?= e(url('pages/' . $pg['slug'])) ?>"><?= e($pg['name']) ?></a><?php if ($pg['verified']): ?> <?= icon('verified', 'zl-verified') ?><?php endif ?><br><small class="zl-faint">/pages/<?= e($pg['slug']) ?></small></div></div></td>
                <td><?= e(t('pages.cat_' . $pg['category'])) ?></td>
                <td><?php if ($ow): ?><a href="<?= e(url('u/' . $ow['username'])) ?>">@<?= e($ow['username']) ?></a><?php endif ?></td>
                <td><?= (int) $pg['followers_count'] ?></td><td><?= (int) $pg['posts_count'] ?></td>
                <td><div class="zl-row" style="justify-content:flex-end;gap:6px">
                    <form method="post" action="<?= e(url('admin/pages/' . $pg['id'] . '/verify')) ?>"><?= csrf_field() ?><button class="zl-btn zl-btn-soft zl-btn-sm"><?= icon('verified', 'zl-i-sm') ?><?= e($pg['verified'] ? t('admin.unverify_short') : t('admin.verify_short')) ?></button></form>
                    <form method="post" action="<?= e(url('pages/' . $pg['slug'] . '/delete')) ?>" data-confirm="<?= e(t('pages.delete_confirm')) ?>"><?= csrf_field() ?><input type="hidden" name="from" value="admin"><button class="zl-btn zl-btn-danger zl-btn-sm"><?= icon('trash', 'zl-i-sm') ?><?= e(t('post.delete')) ?></button></form>
                </div></td>
            </tr><?php endforeach ?>
            <?php if (!$rows): ?><tr><td colspan="6"><div class="zl-empty"><?= icon('page') ?><b><?= e(t('pages.none')) ?></b></div></td></tr><?php endif ?></tbody>
        </table></div>
    </div>
</div>
