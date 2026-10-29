<?php
/** Admin: edit any post - its text, who can see it, its photos (replace, remove, add), hide / show or delete. */
$pid = (int) $p['id'];
$maxImg = max(1, min(10, (int) setting('max_post_images', '4')));
$back = '<input type="hidden" name="_back" value="' . e('admin/posts/' . $pid . '/edit') . '">';
?>
<div class="zl-settings zl-admin">
    <?php View::partial('admin-nav', ['anav' => $anav]); ?>
    <div class="zl-stack">
        <div class="zl-card zl-pad zl-member-head">
            <a class="zl-icon-btn" href="<?= e(url($author ? 'admin/users/' . $author['id'] : 'admin/posts', $author ? ['tab' => 'posts'] : [])) ?>" aria-label="<?= e(t('common.back')) ?>"><?= icon('arrow-left') ?></a>
            <div style="flex:1;min-width:0"><h1 class="zl-h1"><?= e(t('member.edit_post')) ?> #<?= $pid ?></h1>
                <p class="zl-faint"><?php if ($author): ?><a href="<?= e(url('admin/users/' . $author['id'])) ?>">@<?= e($author['username']) ?></a> · <?php endif ?><?= e(Lang::date((int) $p['created_at'])) ?><?php if ($p['edited_at']): ?> · <?= e(t('member.edited')) ?><?php endif ?></p></div>
            <span class="zl-pill<?= $p['status'] === 'published' ? ' is-ok' : ($p['status'] === 'hidden' ? ' is-bad' : ' is-warn') ?>"><?= e(t('member.s_' . $p['status'])) ?></span>
            <a class="zl-btn zl-btn-soft zl-btn-sm" href="<?= e(url('p/' . $pid)) ?>"><?= icon('eye', 'zl-i-sm') ?><?= e(t('admin.view')) ?></a>
        </div>
        <div class="zl-admin-grid">
            <form class="zl-card zl-pad zl-stack" method="post" action="<?= e(url('admin/posts/' . $pid . '/edit')) ?>" enctype="multipart/form-data" data-composer-like>
                <?= csrf_field() ?><input type="hidden" name="format" value="<?= e($p['format'] ?? 'text') ?>">
                <h2 class="zl-h2"><?= icon('edit', 'zl-i-sm') ?> <?= e(t('member.post_text')) ?></h2>
                <?php View::partial('editor', ['name' => 'body', 'id' => 'ap-body', 'value' => (string) $p['body'], 'format' => $p['format'] ?? 'text', 'label' => t('member.post_text'), 'class' => 'zl-input zl-editor-tall']); ?>
                <?php if (!$p['group_id'] && empty($p['page_id']) && empty($p['anonymous'])): ?>
                <label class="zl-field"><span><?= e(t('member.who_sees')) ?></span><select class="zl-input" name="privacy"><?php foreach (Posts::PRIVACY as $pv): ?><option value="<?= e($pv) ?>" <?= $p['privacy'] === $pv ? 'selected' : '' ?>><?= e(t('privacy.' . $pv)) ?></option><?php endforeach ?></select></label>
                <?php endif ?>
                <?php if (count($images) < $maxImg): ?><label class="zl-field"><span><?= e(t('member.add_photos', ['n' => $maxImg - count($images)])) ?></span><input class="zl-input" type="file" name="images[]" accept="image/jpeg,image/png,image/webp,image/gif" multiple></label><?php endif ?>
                <button class="zl-btn zl-btn-main"><?= icon('check', 'zl-i-sm') ?><?= e(t('common.save')) ?></button>
            </form>
            <div class="zl-stack">
                <section class="zl-card zl-pad zl-stack">
                    <h2 class="zl-h2"><?= icon('image', 'zl-i-sm') ?> <?= e(t('member.photos')) ?> · <?= count($images) ?></h2>
                    <div class="zl-media-grid">
                        <?php foreach ($images as $im): ?>
                            <div class="zl-media-item">
                                <div class="zl-media-box"><img src="<?= e(upload_url($im['thumb'])) ?>" alt="" loading="lazy"></div>
                                <div class="zl-row" style="gap:6px;flex-wrap:wrap">
                                    <form method="post" action="<?= e(url('admin/images/' . $im['id'] . '/replace')) ?>" enctype="multipart/form-data"><?= csrf_field() ?><?= $back ?><label class="zl-btn zl-btn-soft zl-btn-sm"><?= icon('upload', 'zl-i-sm') ?><?= e(t('member.replace')) ?><input class="sr-only" type="file" name="image" accept="image/jpeg,image/png,image/webp,image/gif" data-autosubmit></label></form>
                                    <form method="post" action="<?= e(url('admin/images/' . $im['id'] . '/remove')) ?>" data-confirm="<?= e(t('member.remove_photo_confirm')) ?>"><?= csrf_field() ?><?= $back ?><button class="zl-btn zl-btn-soft zl-btn-sm"><?= icon('trash', 'zl-i-sm') ?><?= e(t('trend.remove')) ?></button></form>
                                </div>
                            </div>
                        <?php endforeach ?>
                        <?php if (!$images): ?><p class="zl-faint"><?= e(t('member.no_photos')) ?></p><?php endif ?>
                    </div>
                </section>
                <section class="zl-card zl-pad zl-stack">
                    <h2 class="zl-h2"><?= icon('shield', 'zl-i-sm') ?> <?= e(t('member.moderate')) ?></h2>
                    <div class="zl-row" style="flex-wrap:wrap">
                        <form method="post" action="<?= e(url('admin/posts/' . $pid . '/' . ($p['status'] === 'hidden' ? 'unhide' : 'hide'))) ?>"><?= csrf_field() ?><?= $back ?><button class="zl-btn zl-btn-soft"><?= icon($p['status'] === 'hidden' ? 'eye' : 'eye-off', 'zl-i-sm') ?><?= e($p['status'] === 'hidden' ? t('member.unhide') : t('member.hide')) ?></button></form>
                        <form method="post" action="<?= e(url('admin/posts/' . $pid . '/delete')) ?>" data-confirm="<?= e(t('js.delete_post')) ?>"><?= csrf_field() ?><input type="hidden" name="_back" value="<?= e($author ? 'admin/users/' . $author['id'] . '?tab=posts' : 'admin/posts') ?>"><button class="zl-btn zl-btn-danger"><?= icon('trash', 'zl-i-sm') ?><?= e(t('post.delete')) ?></button></form>
                    </div>
                    <small class="zl-hint"><?= e(t('member.hide_hint')) ?></small>
                </section>
            </div>
        </div>
    </div>
</div>
