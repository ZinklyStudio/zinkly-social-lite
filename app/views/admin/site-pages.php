<?php $form = $edit ?? ($new ? ['id' => 0, 'title' => '', 'slug' => '', 'body' => '', 'in_footer' => 1, 'published' => 1, 'position' => count($rows)] : null); ?>
<div class="zl-settings zl-admin">
    <?php View::partial('admin-nav', ['anav' => $anav]); ?>
    <div class="zl-stack">
        <div class="zl-card zl-pad zl-between" style="flex-wrap:wrap">
            <div><h1 class="zl-h1"><?= e(t('admin.site_pages')) ?></h1><p class="zl-faint"><?= e(t('admin.site_pages_hint')) ?></p></div>
            <a class="zl-btn zl-btn-main zl-btn-sm" href="<?= e(url('admin/site-pages', ['new' => 1])) ?>"><?= icon('plus', 'zl-i-sm') ?><?= e(t('sitepages.new')) ?></a>
        </div>
        <div class="zl-admin-split">
            <div class="zl-card zl-table-wrap"><table class="zl-table">
                <thead><tr><th><?= e(t('sitepages.title')) ?></th><th><?= e(t('admin.status')) ?></th><th><span class="sr-only"><?= e(t('admin.actions')) ?></span></th></tr></thead>
                <tbody><?php foreach ($rows as $r): ?><tr class="<?= $form && (int) $form['id'] === (int) $r['id'] ? 'is-selected' : '' ?>">
                    <td><a class="zl-strong" href="<?= e(url('admin/site-pages', ['edit' => $r['id']])) ?>"><?= e($r['title']) ?></a><br><small class="zl-faint">/info/<?= e($r['slug']) ?></small></td>
                    <td><span class="zl-pill<?= $r['published'] ? ' is-ok' : '' ?>"><?= e($r['published'] ? t('sitepages.published') : t('sitepages.draft')) ?></span><?php if ($r['in_footer']): ?> <span class="zl-pill"><?= e(t('sitepages.footer')) ?></span><?php endif ?></td>
                    <td><div class="zl-row" style="gap:4px;justify-content:flex-end"><a class="zl-icon-btn" href="<?= e(url('info/' . $r['slug'])) ?>" aria-label="<?= e(t('admin.view')) ?>" title="<?= e(t('admin.view')) ?>"><?= icon('eye') ?></a><a class="zl-icon-btn" href="<?= e(url('admin/site-pages', ['edit' => $r['id']])) ?>" aria-label="<?= e(t('post.edit')) ?>" title="<?= e(t('post.edit')) ?>"><?= icon('edit') ?></a></div></td>
                </tr><?php endforeach ?>
                <?php if (!$rows): ?><tr><td colspan="3"><div class="zl-empty"><?= icon('type') ?><b><?= e(t('sitepages.none')) ?></b><span><?= e(t('sitepages.none_hint')) ?></span></div></td></tr><?php endif ?></tbody>
            </table></div>
            <?php if ($form): ?>
            <div class="zl-card zl-pad">
                <form class="zl-stack" method="post" action="<?= e(url('admin/site-pages')) ?>" data-composer-like>
                    <?= csrf_field() ?><input type="hidden" name="id" value="<?= (int) $form['id'] ?>">
                    <h2 class="zl-h2"><?= e($form['id'] ? t('sitepages.edit') : t('sitepages.new')) ?></h2>
                    <div class="zl-grid2">
                        <label class="zl-field"><span><?= e(t('sitepages.title')) ?></span><input class="zl-input" name="title" value="<?= e($form['title']) ?>" maxlength="120" required></label>
                        <label class="zl-field"><span><?= e(t('sitepages.slug')) ?></span><input class="zl-input" name="slug" value="<?= e($form['slug']) ?>" maxlength="80" placeholder="about-us"><small class="zl-hint"><?= e(full_url('info/')) ?>…</small></label>
                    </div>
                    <div class="zl-field"><span><?= e(t('sitepages.body')) ?></span>
                        <?php View::partial('editor', ['name' => 'body', 'id' => 'sp-body', 'value' => (string) $form['body'], 'format' => 'html', 'label' => t('sitepages.body'), 'class' => 'zl-input zl-editor-tall']); ?>
                    </div>
                    <div class="zl-grid2">
                        <label class="zl-check"><span><?= e(t('sitepages.published')) ?><small><?= e(t('sitepages.published_hint')) ?></small></span><span class="zl-switch"><input type="checkbox" name="published" value="1" <?= $form['published'] ? 'checked' : '' ?>><span></span></span></label>
                        <label class="zl-check"><span><?= e(t('sitepages.footer')) ?><small><?= e(t('sitepages.footer_hint')) ?></small></span><span class="zl-switch"><input type="checkbox" name="in_footer" value="1" <?= $form['in_footer'] ? 'checked' : '' ?>><span></span></span></label>
                    </div>
                    <label class="zl-field" style="max-width:200px"><span><?= e(t('sitepages.position')) ?></span><input class="zl-input" type="number" name="position" min="0" max="999" value="<?= (int) $form['position'] ?>"></label>
                    <div class="zl-row"><button class="zl-btn zl-btn-main"><?= icon('check', 'zl-i-sm') ?><?= e(t('common.save')) ?></button><?php if ($form['id']): ?><a class="zl-btn zl-btn-soft" href="<?= e(url('info/' . $form['slug'])) ?>" target="_blank" rel="noopener"><?= icon('eye', 'zl-i-sm') ?><?= e(t('admin.view')) ?></a><?php endif ?></div>
                </form>
                <?php if ($form['id']): ?>
                <form method="post" action="<?= e(url('admin/site-pages/' . (int) $form['id'] . '/delete')) ?>" data-confirm="<?= e(t('sitepages.delete_confirm')) ?>" style="margin-top:14px"><?= csrf_field() ?><button class="zl-btn zl-btn-danger zl-btn-sm"><?= icon('trash', 'zl-i-sm') ?><?= e(t('post.delete')) ?></button></form>
                <?php endif ?>
            </div>
            <?php endif ?>
        </div>
    </div>
</div>
