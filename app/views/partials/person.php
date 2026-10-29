<?php
/** A member row with a follow button. $p = user row, $compact = bool */
$me = Auth::id();
$isMe = (int) $p['id'] === $me;
$st = $me && !$isMe ? Users::followStatus($me, (int) $p['id']) : null;
?>
<div class="zl-person">
    <a href="<?= e(url('u/' . $p['username'])) ?>" aria-label="<?= e(display_name($p)) ?>" class="<?= Users::online($p) ? 'zl-online' : '' ?>"><?= avatar_html($p, empty($compact) ? 'md' : 'sm') ?></a>
    <a class="zl-person-info" href="<?= e(url('u/' . $p['username'])) ?>">
        <b><?= e(display_name($p)) ?><?php if ($p['verified']): ?> <?= icon('verified', 'zl-verified') ?><?php endif ?><?= Pro::badge($p) ?></b>
        <small>@<?= e($p['username']) ?><?php if (empty($compact) && $p['bio'] !== ''): ?> · <?= e(str_limit($p['bio'], 60)) ?><?php endif ?></small>
    </a>
    <?php if ($me && !$isMe): ?>
        <?php View::partial('follow-button', ['u' => $p, 'status' => $st, 'small' => true]); ?>
    <?php endif ?>
</div>
