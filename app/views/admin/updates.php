<?php
$cur = Updater::current();
$tabs = ['update' => ['upload', t('update.tab_update')], 'changelog' => ['file', t('update.tab_changelog')], 'backups' => ['shield', t('update.tab_backups')]];
$reason = fn (string $r) => t('backup.r_' . (in_array($r, ['manual', 'before-update', 'before-restore'], true) ? str_replace('-', '_', $r) : 'manual'));
?>
<div class="zl-settings zl-admin">
    <?php View::partial('admin-nav', ['anav' => $anav]); ?>
    <div class="zl-stack">
        <div class="zl-card zl-pad zl-between" style="flex-wrap:wrap">
            <div><h1 class="zl-h1"><?= e(t('admin.updates')) ?></h1><p class="zl-faint"><?= e(t('update.intro')) ?></p></div>
            <div class="zl-version-badge"><small><?= e(t('update.installed')) ?></small><b>v<?= e($cur) ?></b></div>
        </div>
        <nav class="zl-card zl-tabs" aria-label="<?= e(t('admin.updates')) ?>">
            <?php foreach ($tabs as $k => [$ic, $label]): ?><a href="<?= e(url('admin/updates', $k === 'update' ? [] : ['tab' => $k])) ?>" class="<?= $tab === $k ? 'is-on' : '' ?>"><?= e($label) ?></a><?php endforeach ?>
        </nav>
        <?php if ($problem): ?><p class="zl-alert zl-alert-error"><?= e($problem) ?></p><?php endif ?>

    <?php if ($tab === 'update'): ?>
        <?php if (!empty($_GET['updated']) && $last): ?>
        <section class="zl-card zl-pad zl-update-done">
            <div class="zl-done-ico"><?= icon('check', 'zl-i zl-i-lg') ?></div>
            <h2 class="zl-h2"><?= e(t('update.done_title', ['v' => $cur])) ?></h2>
            <p class="zl-muted"><?= e(t('update.done_text', ['from' => $last['from'], 'backup' => $last['backup']])) ?></p>
            <?php $since = Updater::changelogSince(Updater::changelog(), (string) $last['from']); if ($since !== ''): ?>
                <div class="zl-changelog zl-rich" style="text-align:left;margin-top:16px"><?= Updater::renderMarkdown($since) ?></div>
            <?php endif ?>
        </section>
        <?php endif ?>

        <?php if ($pending): ?>
        <section class="zl-card zl-pad zl-stack zl-pending">
            <div class="zl-between" style="flex-wrap:wrap">
                <h2 class="zl-h2"><?= e(t('update.pending_title')) ?></h2>
                <span class="zl-ver-flow"><span>v<?= e($cur) ?></span><?= icon('arrow-right', 'zl-i-sm') ?><b>v<?= e($pending['version']) ?></b></span>
            </div>
            <?php if ($pending['older']): ?><p class="zl-alert zl-alert-error"><?= e(t('update.older', ['v' => $pending['version']])) ?></p>
            <?php elseif ($pending['same']): ?><p class="zl-alert zl-alert-warn"><?= e(t('update.same')) ?></p><?php endif ?>
            <ul class="zl-reqs">
                <li class="is-ok"><?= icon('check') ?><span><?= e(t('update.chk_package')) ?></span><small><?= e(t('update.n_files', ['n' => $pending['files'], 'size' => Backup::human((int) $pending['bytes'])])) ?></small></li>
                <li class="is-ok"><?= icon('check') ?><span><?= e(t('update.chk_changes')) ?></span><small><?= e(t('update.n_changes', ['changed' => $pending['changed'], 'added' => $pending['added']])) ?></small></li>
                <li class="<?= $pending['php_ok'] ? 'is-ok' : 'is-bad' ?>"><?= icon($pending['php_ok'] ? 'check' : 'x') ?><span>PHP <?= e(PHP_VERSION) ?></span><small><?= e($pending['php_ok'] ? '' : t('update.php_old')) ?></small></li>
                <li class="is-ok"><?= icon('shield') ?><span><?= e(t('update.chk_keep')) ?></span><small>data/ · uploads/ · extensions/</small></li>
                <li class="is-ok"><?= icon('clock') ?><span><?= e(t('update.chk_backup')) ?></span><small><?= e(t('update.chk_backup_hint')) ?></small></li>
            </ul>
            <?php if ($pending['changelog'] !== ''): ?>
                <div><h3 class="zl-h3"><?= e(t('update.whats_new')) ?></h3><div class="zl-changelog zl-rich"><?= Updater::renderMarkdown($pending['changelog']) ?></div></div>
            <?php endif ?>
            <?php if (!$pending['older'] && $pending['php_ok'] && !$problem): ?>
            <form method="post" action="<?= e(url('admin/updates/apply')) ?>" class="zl-stack" data-confirm="<?= e(t('update.confirm', ['v' => $pending['version']])) ?>" data-long-task="<?= e(t('update.working')) ?>">
                <?= csrf_field() ?>
                <?php if ($pending['same']): ?><label class="zl-check"><input type="checkbox" name="reinstall" value="1" required><span><?= e(t('update.reinstall')) ?></span></label><?php endif ?>
                <div class="zl-grid2">
                    <label class="zl-field"><span><?= e(t('update.password')) ?></span><input class="zl-input" type="password" name="password" required autocomplete="current-password"></label>
                    <div class="zl-field"><span>&nbsp;</span><button class="zl-btn zl-btn-main zl-btn-block"><?= icon('upload', 'zl-i-sm') ?><?= e(t('update.apply', ['v' => $pending['version']])) ?></button></div>
                </div>
            </form>
            <?php endif ?>
            <form method="post" action="<?= e(url('admin/updates/cancel')) ?>"><?= csrf_field() ?><button class="zl-btn zl-btn-ghost zl-btn-sm"><?= icon('x', 'zl-i-sm') ?><?= e(t('update.cancel')) ?></button></form>
        </section>
        <?php endif ?>

        <section class="zl-card zl-pad zl-stack">
            <h2 class="zl-h2"><?= e(t('update.upload_title')) ?></h2>
            <p class="zl-muted"><?= e(t('update.upload_hint')) ?></p>
            <form class="zl-upload" method="post" action="<?= e(url('admin/updates/upload')) ?>" enctype="multipart/form-data" data-long-task="<?= e(t('update.checking')) ?>">
                <?= csrf_field() ?>
                <label class="zl-drop" data-drop>
                    <input class="sr-only" type="file" name="package" accept=".zip,application/zip" required data-drop-input>
                    <?= icon('upload', 'zl-i zl-i-lg') ?>
                    <b data-drop-label><?= e(t('update.drop')) ?></b>
                    <small><?= e(t('update.drop_hint', ['mb' => $maxUpload])) ?></small>
                </label>
                <button class="zl-btn zl-btn-main" <?= $problem ? 'disabled' : '' ?>><?= icon('check', 'zl-i-sm') ?><?= e(t('update.check')) ?></button>
            </form>
            <ol class="zl-steps">
                <li><?= e(t('update.step1')) ?></li><li><?= e(t('update.step2')) ?></li><li><?= e(t('update.step3')) ?></li><li><?= e(t('update.step4')) ?></li>
            </ol>
        </section>

        <?php if ($last && empty($_GET['updated'])): ?>
        <p class="zl-faint"><?= icon('clock', 'zl-i-sm') ?> <?= e(t('update.last', ['from' => $last['from'], 'to' => $last['to'], 'date' => Lang::date((int) $last['at']) . ' ' . date('H:i', (int) $last['at'])])) ?></p>
        <?php endif ?>

    <?php elseif ($tab === 'changelog'): ?>
        <section class="zl-card zl-pad">
            <?php $md = Updater::changelog(); ?>
            <?php if ($md === ''): ?><p class="zl-faint"><?= e(t('update.no_changelog')) ?></p><?php else: ?>
            <div class="zl-changelog zl-rich"><?= Updater::renderMarkdown(preg_replace('/^# .*\n/', '', $md) ?? $md) ?></div>
            <?php endif ?>
        </section>

    <?php else: ?>
        <section class="zl-card zl-pad zl-stack">
            <h2 class="zl-h2"><?= e(t('backup.create_title')) ?></h2>
            <p class="zl-muted"><?= e(t('backup.intro')) ?></p>
            <form method="post" action="<?= e(url('admin/backups')) ?>" class="zl-stack" data-long-task="<?= e(t('backup.working')) ?>">
                <?= csrf_field() ?>
                <div class="zl-switch-grid">
                    <?php foreach (['database' => '1', 'files' => '1', 'uploads' => ''] as $part => $on): ?>
                    <label class="zl-check"><span><?= e(t('backup.p_' . $part)) ?><small><?= e(t('backup.p_' . $part . '_hint')) ?></small></span><span class="zl-switch"><input type="checkbox" name="parts[<?= $part ?>]" value="1" <?= $on ? 'checked' : '' ?>><span></span></span></label>
                    <?php endforeach ?>
                </div>
                <div class="zl-row" style="flex-wrap:wrap"><button class="zl-btn zl-btn-main" <?= $problem ? 'disabled' : '' ?>><?= icon('shield', 'zl-i-sm') ?><?= e(t('backup.create')) ?></button>
                    <?php if ($free !== null): ?><small class="zl-faint"><?= e(t('backup.free', ['size' => Backup::human($free)])) ?></small><?php endif ?></div>
            </form>
        </section>

        <section class="zl-card zl-table-wrap">
            <table class="zl-table">
                <thead><tr><th><?= e(t('backup.when')) ?></th><th><?= e(t('backup.contents')) ?></th><th><?= e(t('backup.version')) ?></th><th><?= e(t('backup.size')) ?></th><th><span class="sr-only"><?= e(t('admin.actions')) ?></span></th></tr></thead>
                <tbody>
                <?php foreach ($backups as $b): $m = $b['manifest']; ?>
                    <tr>
                        <td><b class="zl-strong"><?= e(Lang::date($b['time']) . ' ' . date('H:i', $b['time'])) ?></b><br><small class="zl-faint"><?= e($m ? $reason((string) $m['reason']) : t('backup.unknown')) ?></small></td>
                        <td><?php if ($m): foreach ($m['parts'] as $part): ?><span class="zl-pill"><?= e(t('backup.p_' . $part)) ?></span> <?php endforeach; endif ?></td>
                        <td class="zl-faint">v<?= e((string) ($m['version'] ?? '?')) ?></td>
                        <td class="zl-faint zl-nowrap"><?= e(Backup::human((int) $b['size'])) ?></td>
                        <td>
                            <div class="zl-row" style="justify-content:flex-end;gap:6px">
                                <a class="zl-btn zl-btn-soft zl-btn-sm" href="<?= e(url('admin/backups/' . $b['name'] . '/download')) ?>"><?= icon('upload', 'zl-i-sm zl-flip') ?><?= e(t('backup.download')) ?></a>
                                <?php if ($m): ?><button type="button" class="zl-btn zl-btn-soft zl-btn-sm" data-open="restore-<?= e(md5($b['name'])) ?>"><?= icon('repeat', 'zl-i-sm') ?><?= e(t('backup.restore')) ?></button><?php endif ?>
                                <form method="post" action="<?= e(url('admin/backups/' . $b['name'] . '/delete')) ?>" data-confirm="<?= e(t('backup.delete_confirm')) ?>"><?= csrf_field() ?><button class="zl-icon-btn" aria-label="<?= e(t('post.delete')) ?>" title="<?= e(t('post.delete')) ?>"><?= icon('trash') ?></button></form>
                            </div>
                            <?php if ($m): ?>
                            <div class="zl-modal" id="restore-<?= e(md5($b['name'])) ?>" hidden role="dialog" aria-modal="true" aria-labelledby="rt-<?= e(md5($b['name'])) ?>">
                                <form class="zl-sheet" method="post" action="<?= e(url('admin/backups/' . $b['name'] . '/restore')) ?>" data-long-task="<?= e(t('backup.restoring')) ?>">
                                    <?= csrf_field() ?>
                                    <div class="zl-sheet-head"><b id="rt-<?= e(md5($b['name'])) ?>"><?= e(t('backup.restore_title')) ?></b><button type="button" class="zl-icon-btn" data-close aria-label="<?= e(t('common.close')) ?>"><?= icon('x') ?></button></div>
                                    <div class="zl-sheet-body">
                                        <p class="zl-muted"><?= e(t('backup.restore_text', ['date' => Lang::date($b['time']) . ' ' . date('H:i', $b['time']), 'v' => (string) ($m['version'] ?? '?')])) ?></p>
                                        <p class="zl-alert zl-alert-warn"><?= e(t('backup.restore_warn')) ?></p>
                                        <?php if (in_array('uploads', $m['parts'], true)): ?><label class="zl-check"><input type="checkbox" name="with_uploads" value="1"><span><?= e(t('backup.restore_uploads')) ?></span></label><?php endif ?>
                                        <label class="zl-field"><span><?= e(t('update.password')) ?></span><input class="zl-input" type="password" name="password" required autocomplete="current-password"></label>
                                        <button class="zl-btn zl-btn-danger zl-btn-block"><?= icon('repeat', 'zl-i-sm') ?><?= e(t('backup.restore_now')) ?></button>
                                    </div>
                                </form>
                            </div>
                            <?php endif ?>
                        </td>
                    </tr>
                <?php endforeach ?>
                <?php if (!$backups): ?><tr><td colspan="5"><div class="zl-empty"><?= icon('shield') ?><b><?= e(t('backup.none')) ?></b><span><?= e(t('backup.none_hint')) ?></span></div></td></tr><?php endif ?>
                </tbody>
            </table>
        </section>
        <p class="zl-hint"><?= icon('lock', 'zl-i-sm') ?> <?= e(t('backup.private')) ?></p>
    <?php endif ?>
    </div>
</div>
