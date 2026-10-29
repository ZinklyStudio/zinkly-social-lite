<?php /** Shared dialogs: share, report, add story, confirm, photo viewer, story viewer. */ ?>
<div class="zl-modal" id="share-modal" hidden role="dialog" aria-modal="true" aria-labelledby="share-title">
    <form class="zl-sheet" method="post" data-share-form>
        <?= csrf_field() ?>
        <div class="zl-sheet-head"><b id="share-title"><?= e(t('post.share')) ?></b><button type="button" class="zl-icon-btn" data-close aria-label="<?= e(t('common.close')) ?>"><?= icon('x') ?></button></div>
        <div class="zl-sheet-body">
            <p class="zl-muted"><?= e(t('post.share_hint')) ?></p>
            <label class="sr-only" for="share-body"><?= e(t('post.share_say')) ?></label>
            <textarea id="share-body" class="zl-input" name="body" maxlength="<?= Posts::MAX_BODY ?>" placeholder="<?= e(t('post.share_say')) ?>"></textarea>
            <button class="zl-btn zl-btn-main zl-btn-block"><?= icon('repeat') ?><?= e(t('post.share_now')) ?></button>
        </div>
    </form>
</div>

<div class="zl-modal" id="report-modal" hidden role="dialog" aria-modal="true" aria-labelledby="report-title">
    <form class="zl-sheet" method="post" action="<?= e(url('report')) ?>" data-report-form>
        <?= csrf_field() ?>
        <input type="hidden" name="type" value=""><input type="hidden" name="id" value="">
        <div class="zl-sheet-head"><b id="report-title"><?= e(t('report.title')) ?></b><button type="button" class="zl-icon-btn" data-close aria-label="<?= e(t('common.close')) ?>"><?= icon('x') ?></button></div>
        <div class="zl-sheet-body">
            <p class="zl-muted"><?= e(t('report.hint')) ?></p>
            <?php foreach (Reports::REASONS as $i => $r): ?>
                <label class="zl-check"><input type="radio" name="reason" value="<?= e($r) ?>" <?= $i === 0 ? 'required' : '' ?>><span><?= e(t('report.r_' . $r)) ?></span></label>
            <?php endforeach ?>
            <label class="sr-only" for="report-details"><?= e(t('report.details')) ?></label>
            <textarea id="report-details" class="zl-input" name="details" maxlength="500" placeholder="<?= e(t('report.details')) ?>"></textarea>
            <button class="zl-btn zl-btn-main zl-btn-block"><?= e(t('report.send')) ?></button>
        </div>
    </form>
</div>

<?php if (setting('stories_enabled', '1') === '1'): $stVideo = Stories::videoEnabled(); ?>
<div class="zl-modal" id="story-modal" hidden role="dialog" aria-modal="true" aria-labelledby="story-title">
    <form class="zl-sheet zl-st-sheet" method="post" action="<?= e(url('stories')) ?>" enctype="multipart/form-data" data-story-form
        data-photos-url="<?= e(url('stories/my-photos')) ?>" data-video-mb="<?= Stories::videoMaxMb() ?>" data-video-sec="<?= max(5, (int) setting('story_video_seconds', '60')) ?>" data-busy-text="<?= e(t('story.uploading')) ?>">
        <?= csrf_field() ?>
        <input type="hidden" name="from_image" value="" data-st-from>
        <div class="zl-sheet-head"><b id="story-title"><?= e(t('story.add')) ?></b><button type="button" class="zl-icon-btn" data-close aria-label="<?= e(t('common.close')) ?>"><?= icon('x') ?></button></div>
        <div class="zl-sheet-body zl-st-body">
            <div class="zl-st-preview zl-bg-violet zl-tc-white zl-font-modern zl-pos-bottom" data-st-preview aria-hidden="true">
                <img alt="" hidden data-st-img>
                <video muted loop playsinline hidden data-st-video></video>
                <div class="zl-st-empty" data-st-empty><?= icon('camera', 'zl-i zl-i-lg') ?><span><?= e($stVideo ? t('story.pick_media') : t('story.pick_photo')) ?></span></div>
                <div class="zl-st-cap" data-st-cap></div>
            </div>
            <div class="zl-stack zl-st-controls">
                <div class="zl-seg" role="radiogroup" aria-label="<?= e(t('story.type')) ?>">
                    <input type="radio" name="type" value="image" id="st-image" class="sr-only" checked><label for="st-image"><?= icon('image', 'zl-i-sm') ?> <?= e($stVideo ? t('story.photo_video') : t('story.photo')) ?></label>
                    <input type="radio" name="type" value="text" id="st-text" class="sr-only"><label for="st-text"><?= icon('type', 'zl-i-sm') ?> <?= e(t('story.text')) ?></label>
                </div>

                <div class="zl-stack" data-st-panel="image">
                    <div class="zl-st-sources">
                        <label class="zl-st-src"><?= icon('upload') ?><span><?= e(t('story.src_upload')) ?></span><input class="sr-only" type="file" name="media[]" data-st-file accept="image/jpeg,image/png,image/webp,image/gif<?= $stVideo ? ',video/mp4,video/webm,video/quicktime' : '' ?>"></label>
                        <label class="zl-st-src"><?= icon('camera') ?><span><?= e(t('story.src_camera')) ?></span><input class="sr-only" type="file" name="media[]" data-st-file accept="image/*" capture="environment"></label>
                        <?php if ($stVideo): ?><label class="zl-st-src"><?= icon('video') ?><span><?= e(t('story.src_video')) ?></span><input class="sr-only" type="file" name="media[]" data-st-file accept="video/*" capture="environment"></label><?php endif ?>
                        <button type="button" class="zl-st-src" data-st-posts><?= icon('file') ?><span><?= e(t('story.src_posts')) ?></span></button>
                    </div>
                    <div class="zl-st-grid" data-st-grid hidden></div>
                    <?php if ($stVideo): ?><p class="zl-hint"><?= e(t('story.video_hint', ['mb' => Stories::videoMaxMb(), 's' => max(5, (int) setting('story_video_seconds', '60'))])) ?></p><?php endif ?>
                </div>

                <label class="zl-field"><span data-st-label data-l-image="<?= e(t('story.caption')) ?>" data-l-text="<?= e(t('story.your_text')) ?>"><?= e(t('story.caption')) ?></span>
                    <textarea class="zl-input" name="text" maxlength="300" rows="2" placeholder="<?= e(t('story.text_ph')) ?>" data-st-text></textarea></label>

                <div class="zl-field"><span><?= e(t('story.text_color')) ?></span>
                    <div class="zl-bg-pick zl-tc-pick" role="radiogroup" aria-label="<?= e(t('story.text_color')) ?>">
                        <?php foreach (Stories::COLORS as $i => $c): ?><input type="radio" class="sr-only" name="color" value="<?= e($c) ?>" id="tc-<?= e($c) ?>" <?= $i === 0 ? 'checked' : '' ?> data-st-style="tc"><label for="tc-<?= e($c) ?>" class="zl-sw-<?= e($c) ?>" title="<?= e(t('story.c_' . $c)) ?>"><span class="sr-only"><?= e(t('story.c_' . $c)) ?></span></label><?php endforeach ?>
                    </div>
                </div>
                <div class="zl-field"><span><?= e(t('story.font')) ?></span>
                    <div class="zl-chips zl-st-chips" role="radiogroup" aria-label="<?= e(t('story.font')) ?>">
                        <?php foreach (Stories::FONTS as $i => $f): ?><input type="radio" class="sr-only" name="font" value="<?= e($f) ?>" id="sf-<?= e($f) ?>" <?= $i === 0 ? 'checked' : '' ?> data-st-style="font"><label for="sf-<?= e($f) ?>" class="zl-chip zl-font-<?= e($f) ?>"><?= e(t('story.f_' . $f)) ?></label><?php endforeach ?>
                    </div>
                </div>
                <div class="zl-field" data-st-panel="image"><span><?= e(t('story.position')) ?></span>
                    <div class="zl-chips zl-st-chips" role="radiogroup" aria-label="<?= e(t('story.position')) ?>">
                        <?php foreach (Stories::POSITIONS as $pz): ?><input type="radio" class="sr-only" name="pos" value="<?= e($pz) ?>" id="sp-<?= e($pz) ?>" <?= $pz === 'bottom' ? 'checked' : '' ?> data-st-style="pos"><label for="sp-<?= e($pz) ?>" class="zl-chip"><?= e(t('story.p_' . $pz)) ?></label><?php endforeach ?>
                    </div>
                </div>
                <div class="zl-field" data-st-panel="text" hidden><span><?= e(t('story.background')) ?></span>
                    <div class="zl-bg-pick" role="radiogroup" aria-label="<?= e(t('story.background')) ?>">
                        <?php foreach (Stories::BGS as $i => $bg): ?><input type="radio" class="sr-only" name="bg" value="<?= e($bg) ?>" id="bg-<?= e($bg) ?>" <?= $i === 0 ? 'checked' : '' ?> data-st-style="bg"><label for="bg-<?= e($bg) ?>" class="zl-bg-<?= e($bg) ?>" title="<?= e($bg) ?>"><span class="sr-only"><?= e($bg) ?></span></label><?php endforeach ?>
                    </div>
                </div>
                <p class="zl-hint"><?= e(t('story.hint')) ?></p>
                <button class="zl-btn zl-btn-main zl-btn-block" data-st-submit><?= e(t('story.share')) ?></button>
            </div>
        </div>
    </form>
</div>
<?php endif ?>

<div class="zl-modal" id="confirm-modal" hidden role="alertdialog" aria-modal="true" aria-labelledby="confirm-title">
    <div class="zl-sheet">
        <div class="zl-sheet-head"><b id="confirm-title"><?= e(t('common.are_you_sure')) ?></b><button type="button" class="zl-icon-btn" data-close aria-label="<?= e(t('common.close')) ?>"><?= icon('x') ?></button></div>
        <div class="zl-sheet-body">
            <p class="zl-muted" data-confirm-text></p>
            <div class="zl-row" style="justify-content:flex-end"><button type="button" class="zl-btn zl-btn-soft" data-close><?= e(t('common.cancel')) ?></button><button type="button" class="zl-btn zl-btn-danger" data-confirm-yes><?= e(t('post.delete')) ?></button></div>
        </div>
    </div>
</div>

<div class="zl-story-viewer" id="story-viewer" hidden role="dialog" aria-modal="true" aria-label="<?= e(t('story.stories')) ?>">
    <div class="zl-story-frame">
        <div class="zl-story-top">
            <div class="zl-story-bars" data-bars></div>
            <div class="zl-between">
                <div class="zl-row"><span data-avatar></span><div><a data-name href="#"></a><br><small data-ago></small></div></div>
                <div class="zl-row" style="gap:0">
                    <button type="button" class="zl-icon-btn" data-story-delete hidden aria-label="<?= e(t('story.delete')) ?>"><?= icon('trash') ?></button>
                    <button type="button" class="zl-icon-btn" data-story-close aria-label="<?= e(t('common.close')) ?>"><?= icon('x') ?></button>
                </div>
            </div>
        </div>
        <div data-stage style="width:100%;height:100%"></div>
        <button type="button" class="zl-story-nav is-prev" data-story-prev aria-label="<?= e(t('common.previous')) ?>"></button>
        <button type="button" class="zl-story-nav is-next" data-story-next aria-label="<?= e(t('common.next')) ?>"></button>
        <div class="zl-story-foot" data-views hidden><?= icon('eye', 'zl-i-sm') ?><span></span></div>
    </div>
</div>

<div class="zl-story-viewer" id="lightbox" hidden role="dialog" aria-modal="true" aria-label="<?= e(t('post.open_photo')) ?>">
    <div class="zl-story-frame" style="background:#000">
        <div class="zl-story-top"><div class="zl-between"><span data-lb-count></span><button type="button" class="zl-icon-btn" data-lb-close aria-label="<?= e(t('common.close')) ?>"><?= icon('x') ?></button></div></div>
        <img data-lb-img src="data:image/gif;base64,R0lGODlhAQABAAAAACw=" alt="">
        <button type="button" class="zl-story-nav is-prev" data-lb-prev aria-label="<?= e(t('common.previous')) ?>"></button>
        <button type="button" class="zl-story-nav is-next" data-lb-next aria-label="<?= e(t('common.next')) ?>"></button>
    </div>
</div>
