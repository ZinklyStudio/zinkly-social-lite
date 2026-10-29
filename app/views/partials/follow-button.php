<?php
/** Follow / Following / Requested button. $u = target, $status = null|'accepted'|'pending' */
$labelFor = ['accepted' => t('profile.following_btn'), 'pending' => t('profile.requested'), '' => $u['private_account'] ? t('profile.request_follow') : t('profile.follow')];
$st = $status ?? '';
?>
<button type="button" class="zl-btn <?= $st ? 'zl-btn-soft' : 'zl-btn-main' ?><?= !empty($small) ? ' zl-btn-sm' : '' ?>"
        data-follow="<?= e(url('u/' . $u['username'] . '/follow')) ?>" data-unfollow="<?= e(url('u/' . $u['username'] . '/unfollow')) ?>"
        data-state="<?= e($st ?: 'none') ?>" data-l-none="<?= e($labelFor['']) ?>" data-l-accepted="<?= e($labelFor['accepted']) ?>" data-l-pending="<?= e($labelFor['pending']) ?>"><?= e($labelFor[$st]) ?></button>
