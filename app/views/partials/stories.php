<?php
/** Story tray on the home page. $tray = Stories::tray() */
$me = Auth::user();
$mine = null;
foreach ($tray as $item) {
    if ((int) $item['user']['id'] === (int) $me['id']) {
        $mine = $item;
    }
}
?>
<section class="zl-card" aria-label="<?= e(t('story.stories')) ?>">
    <div class="zl-tray">
        <?php if (!$mine): ?>
            <button type="button" class="zl-tray-item" data-open="story-modal">
                <span class="zl-ring zl-ring-add"><?= avatar_html($me, 'lg') ?><b><?= icon('plus') ?></b></span>
                <span><?= e(t('story.your')) ?></span>
            </button>
        <?php endif ?>
        <?php foreach ($tray as $item): $tu = $item['user']; $isMe = (int) $tu['id'] === (int) $me['id']; ?>
            <button type="button" class="zl-tray-item" data-story-user="<?= (int) $tu['id'] ?>" data-src="<?= e(url('stories/' . (int) $tu['id'])) ?>">
                <span class="zl-ring<?= $item['all_seen'] && !$isMe ? ' is-seen' : '' ?>"><?= avatar_html($tu, 'lg') ?></span>
                <span><?= e($isMe ? t('story.your') : display_name($tu)) ?></span>
            </button>
        <?php endforeach ?>
        <?php if ($mine): ?>
            <button type="button" class="zl-tray-item" data-open="story-modal">
                <span class="zl-ring zl-ring-add"><span class="zl-av zl-av-lg"><?= icon('plus') ?></span></span>
                <span><?= e(t('story.add')) ?></span>
            </button>
        <?php endif ?>
    </div>
</section>
