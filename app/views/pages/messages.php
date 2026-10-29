<?php $me = Auth::id(); ?>
<div class="zl-card zl-msg-wrap<?= $conv ? ' has-conv' : '' ?>">
    <aside class="zl-inbox" aria-label="<?= e(t('msg.chats')) ?>">
        <div class="zl-pad" style="padding:14px 16px 8px"><h1 class="zl-h1"><?= e(t('nav.messages')) ?></h1></div>
        <?php if (!$inbox): ?><p class="zl-faint" style="padding:8px 16px"><?= e(t('msg.no_chats')) ?></p><?php endif ?>
        <?php foreach ($inbox as $c): $o = $c['other']; ?>
            <a href="<?= e(url('messages/' . $c['id'])) ?>" class="<?= $conv && (int) $conv['id'] === (int) $c['id'] ? 'is-on' : '' ?><?= $c['unread'] ? ' is-unread' : '' ?>">
                <span class="<?= Users::online($o) ? 'zl-online' : '' ?>"><?= avatar_html($o, 'md') ?></span>
                <p><b><?= e(display_name($o)) ?></b><small><?= $c['last'] ? ((int) $c['last']['user_id'] === $me ? e(t('msg.you')) . ': ' : '') . e($c['last']['body'] !== null ? str_limit($c['last']['body'], 50) : t('msg.photo')) : '' ?></small></p>
                <?php if ($c['unread']): ?><span class="zl-count"><?= (int) $c['unread'] ?></span><?php endif ?>
            </a>
        <?php endforeach ?>
    </aside>
    <section class="zl-thread">
        <?php if (!$conv): ?>
            <div class="zl-msg-empty">
                <div class="zl-msg-hero">
                    <span class="zl-msg-hero-ico"><?= icon('chat', 'zl-i zl-i-lg') ?></span>
                    <h2 class="zl-h1"><?= e(t('msg.pick')) ?></h2>
                    <p class="zl-muted"><?= e($inbox ? t('msg.pick_hint') : t('msg.start_hint')) ?></p>
                    <?php if (!empty($starters)): ?>
                    <div class="zl-msg-start">
                        <h3><?= e(t('msg.start_with')) ?></h3>
                        <?php foreach ($starters as $s): ?>
                            <a class="zl-msg-start-row" href="<?= e(url('messages/new/' . $s['username'])) ?>">
                                <span class="<?= Users::online($s) ? 'zl-online' : '' ?>"><?= avatar_html($s, 'sm') ?></span>
                                <span class="zl-msg-start-name"><b><?= e(display_name($s)) ?><?php if ($s['verified']): ?> <?= icon('verified', 'zl-verified') ?><?php endif ?></b><small>@<?= e($s['username']) ?></small></span>
                                <span class="zl-btn zl-btn-soft zl-btn-sm"><?= icon('send', 'zl-i-sm') ?><?= e(t('msg.message')) ?></span>
                            </a>
                        <?php endforeach ?>
                    </div>
                    <?php endif ?>
                    <a class="zl-btn zl-btn-main" href="<?= e(url('search')) ?>"><?= icon('search', 'zl-i-sm') ?><?= e(t('msg.find_people')) ?></a>
                </div>
            </div>
        <?php else: ?>
            <header class="zl-thread-head">
                <a class="zl-icon-btn" href="<?= e(url('messages')) ?>" aria-label="<?= e(t('common.back')) ?>"><?= icon('arrow-left') ?></a>
                <?php if ($other): ?>
                <a href="<?= e(url('u/' . $other['username'])) ?>" class="<?= Users::online($other) ? 'zl-online' : '' ?>"><?= avatar_html($other, 'sm') ?></a>
                <a href="<?= e(url('u/' . $other['username'])) ?>"><b class="zl-strong"><?= e(display_name($other)) ?></b><br><small class="zl-faint"><?= Users::online($other) ? e(t('msg.online')) : e(t('msg.last_seen', ['time' => ago((int) $other['last_seen'])])) ?></small></a>
                <?php endif ?>
            </header>
            <div class="zl-thread-body" data-thread="<?= (int) $conv['id'] ?>" data-poll="<?= e(url('messages/' . $conv['id'] . '/poll')) ?>" aria-live="polite">
                <?php foreach ($msgs as $m): $j = Messages::json($m, $me); ?>
                    <div class="zl-bubble<?= $j['mine'] ? ' is-mine' : '' ?>" data-mid="<?= $j['id'] ?>"><?php if ($j['image']): ?><img src="<?= e($j['image']) ?>" alt="" loading="lazy"><?php endif ?><?= $j['html'] ?><time><?= e($j['time']) ?></time></div>
                <?php endforeach ?>
                <?php if (!$msgs): ?><p class="zl-faint zl-center" data-thread-empty><?= e(t('msg.say_hi')) ?></p><?php endif ?>
            </div>
            <p class="zl-hint" data-seen hidden style="text-align:right;padding:0 16px 4px"><?= e(t('msg.seen')) ?></p>
            <?php if ($canSend): ?>
            <form class="zl-thread-form" method="post" action="<?= e(url('messages/' . $conv['id'] . '/send')) ?>" enctype="multipart/form-data" data-msg-form>
                <?= csrf_field() ?>
                <label class="zl-icon-btn" title="<?= e(t('post.photo')) ?>"><?= icon('image') ?><span class="sr-only"><?= e(t('post.photo')) ?></span><input class="sr-only" type="file" name="image" accept="image/jpeg,image/png,image/webp,image/gif"></label>
                <label class="sr-only" for="msg-body"><?= e(t('msg.write')) ?></label>
                <textarea id="msg-body" class="zl-input" name="body" rows="1" maxlength="<?= Messages::MAX ?>" placeholder="<?= e(t('msg.write')) ?>"></textarea>
                <button class="zl-icon-btn" style="color:var(--violet2)" aria-label="<?= e(t('msg.send')) ?>"><?= icon('send') ?></button>
            </form>
            <?php else: ?>
            <p class="zl-note" style="margin:10px"><?= e(t('msg.cannot_message')) ?></p>
            <?php endif ?>
        <?php endif ?>
    </section>
</div>
