<?php
/** The post box. $groupId / $group optional, $pageId / $page optional (post as that page); $idSuffix makes ids unique when the box appears twice. */
$me = Auth::user();
$sfx = $idSuffix ?? 'main';
$maxImg = (int) setting('max_post_images', '4');
$group = $group ?? (!empty($groupId) ? Groups::find((int) $groupId) : null);
$anonOk = setting('anonymous_posts', '1') === '1' && empty($pageId) && (!$group || !empty($group['allow_anonymous']));
$myPages = (empty($groupId) && empty($pageId) && Pages::enabled()) ? Pages::mine((int) $me['id']) : [];
$asPage = !empty($pageId) ? ($page ?? Pages::find((int) $pageId)) : null;
?>
<form class="zl-card zl-composer" method="post" action="<?= e(url('posts')) ?>" enctype="multipart/form-data" data-composer data-draft="<?= e($sfx . ':' . ($groupId ?? $pageId ?? 0)) ?>">
    <?= csrf_field() ?>
    <?php if (!empty($groupId)): ?><input type="hidden" name="group_id" value="<?= (int) $groupId ?>"><?php endif ?>
    <?php if ($asPage): ?><input type="hidden" name="page_id" value="<?= (int) $asPage['id'] ?>"><?php endif ?>
    <div class="zl-row" style="align-items:flex-start">
        <span data-composer-avatar><?= $asPage ? Pages::avatar($asPage, 'md') : avatar_html($me, 'md') ?></span>
        <div style="flex:1;min-width:0">
            <?php View::partial('editor', ['name' => 'body', 'id' => 'body-' . $sfx, 'value' => '', 'class' => 'zl-composer-text', 'max' => Posts::maxBody(),
                'label' => t('post.placeholder', ['name' => $asPage ? $asPage['name'] : display_name($me)]),
                'placeholder' => $asPage ? t('pages.placeholder', ['name' => $asPage['name']]) : t('post.placeholder', ['name' => display_name($me)])]); ?>
        </div>
    </div>
    <div class="zl-previews" data-previews hidden></div>
    <?php if ($group && !empty($group['post_approval']) && !Groups::isManager((int) $group['id'], (int) $me['id'])): ?><p class="zl-hint" style="margin:0 0 8px"><?= icon('clock', 'zl-i-sm') ?> <?= e(t('group.approval_hint')) ?></p><?php endif ?>
    <div class="zl-composer-bar">
        <label class="zl-pick zl-pick-photo">
            <?= icon('image') ?><span><?= e(t('post.photo')) ?></span>
            <input class="sr-only" type="file" name="images[]" accept="image/jpeg,image/png,image/webp,image/gif" multiple data-max="<?= $maxImg ?>">
        </label>
        <?php if (empty($groupId) && empty($pageId) && setting('stories_enabled', '1') === '1'): ?>
        <button type="button" class="zl-pick zl-pick-story" data-open="story-modal"><?= icon('camera') ?><span><?= e(t('story.add')) ?></span></button>
        <?php endif ?>
        <div class="zl-menu">
            <button type="button" class="zl-pick zl-pick-emoji" data-menu aria-haspopup="true" aria-expanded="false" aria-label="<?= e(t('post.emoji')) ?>"><?= icon('smile') ?><span class="zl-hide-sm"><?= e(t('post.emoji')) ?></span></button>
            <div class="zl-menu-list zl-emoji-list" hidden data-emoji-list></div>
        </div>
        <?php if ($anonOk): ?>
        <label class="zl-pick zl-pick-anon" title="<?= e(t('anon.hint')) ?>"><input type="checkbox" name="anonymous" value="1" class="sr-only" data-anon-toggle><?= icon('mask') ?><span><?= e(t('anon.post')) ?></span></label>
        <?php endif ?>
        <?= Ext::html('composer_tools', $groupId ?? null, $pageId ?? null) ?>
        <div class="zl-push">
            <small class="zl-counter" data-counter hidden></small>
            <?php if ($myPages): ?>
            <label class="sr-only" for="as-<?= e($sfx) ?>"><?= e(t('pages.post_as')) ?></label>
            <select id="as-<?= e($sfx) ?>" name="page_id" class="zl-input zl-privacy-sel" data-post-as title="<?= e(t('pages.post_as')) ?>">
                <option value=""><?= e(display_name($me)) ?></option>
                <?php foreach ($myPages as $mp): ?><option value="<?= (int) $mp['id'] ?>"><?= e($mp['name']) ?></option><?php endforeach ?>
            </select>
            <?php endif ?>
            <?php if (empty($groupId) && !$asPage): ?>
            <label class="sr-only" for="priv-<?= e($sfx) ?>"><?= e(t('privacy.who')) ?></label>
            <select id="priv-<?= e($sfx) ?>" name="privacy" class="zl-input zl-privacy-sel" data-privacy><?php foreach (Posts::PRIVACY as $pv): ?><option value="<?= e($pv) ?>"><?= e(t('privacy.' . $pv)) ?></option><?php endforeach ?></select>
            <?php endif ?>
            <button class="zl-btn zl-btn-main zl-btn-sm"><?= e(t('post.publish')) ?></button>
        </div>
    </div>
</form>
