<?php
$installed = [];
foreach ($list as $x) {
    $installed[(string) $x['row']['ext_id']] = $x;
}
?>
<div class="zl-settings zl-admin">
    <?php View::partial('admin-nav', ['anav' => $anav]); ?>
    <div class="zl-stack">
        <div class="zl-card zl-pad">
            <div class="zl-between" style="flex-wrap:wrap;align-items:flex-start">
                <div><h1 class="zl-h1"><?= e(t('admin.extensions')) ?></h1><p class="zl-faint"><?= e(t('ext.intro')) ?></p></div>
            </div>
            <?php if (setting('extensions_enabled', '1') !== '1'): ?><p class="zl-alert zl-alert-warn" style="margin-top:12px"><?= e(t('ext.all_off')) ?> <a class="zl-link" href="<?= e(url('admin/settings#features')) ?>"><?= e(t('admin.settings')) ?></a></p><?php endif ?>
            <?php if (!$canZip): ?><p class="zl-alert zl-alert-error" style="margin-top:12px"><?= e(t('ext.no_zip')) ?></p><?php elseif (!$writable): ?><p class="zl-alert zl-alert-error" style="margin-top:12px"><?= e(t('ext.not_writable')) ?></p><?php endif ?>
            <form class="zl-upload" method="post" action="<?= e(url('admin/extensions')) ?>" enctype="multipart/form-data" data-upload-form>
                <?= csrf_field() ?>
                <label class="zl-drop" data-drop>
                    <input class="sr-only" type="file" name="package" accept=".zip,application/zip" required data-drop-input>
                    <?= icon('upload', 'zl-i zl-i-lg') ?>
                    <b data-drop-label><?= e(t('ext.drop')) ?></b>
                    <small><?= e(t('ext.drop_hint', ['mb' => (int) (Ext::MAX_ZIP / 1048576)])) ?></small>
                </label>
                <button class="zl-btn zl-btn-main" <?= $canZip && $writable ? '' : 'disabled' ?>><?= icon('upload', 'zl-i-sm') ?><?= e(t('ext.install')) ?></button>
            </form>
            <p class="zl-hint" style="margin-top:10px"><?= icon('shield', 'zl-i-sm') ?> <?= e(t('ext.trust')) ?></p>
        </div>

        <?php foreach (Ext::FEATURED as $fid => $feat): if (isset($installed[$fid])) { continue; } ?>
        <div class="zl-card zl-pad zl-featured-ext">
            <span class="zl-ext-ico is-featured"><?= icon($feat['icon']) ?></span>
            <div style="flex:1;min-width:0">
                <b class="zl-strong"><?= e($feat['name']) ?></b> <span class="zl-pill"><?= e(t('ext.not_installed')) ?></span>
                <p class="zl-muted" style="font-size:14px;margin-top:4px"><?= e(t('ext.f_' . $fid)) ?></p>
                <p class="zl-hint" style="margin-top:6px"><?= e(t('ext.f_' . $fid . '_how')) ?></p>
            </div>
        </div>
        <?php endforeach ?>

        <?php if (!$list): ?>
            <div class="zl-card zl-empty"><?= icon('puzzle') ?><b><?= e(t('ext.none')) ?></b><span><?= e(t('ext.none_hint')) ?></span></div>
        <?php endif ?>

        <?php foreach ($list as $x): $row = $x['row']; $m = $x['manifest']; $id = (string) $row['ext_id']; $on = (bool) $row['enabled']; $isOpen = $open === $id;
            $vals = json_decode((string) $row['settings'], true) ?: []; ?>
        <section class="zl-card zl-ext<?= $on ? ' is-on' : '' ?>" id="ext-<?= e($id) ?>">
            <div class="zl-pad zl-ext-head">
                <span class="zl-ext-ico<?= isset(Ext::FEATURED[$id]) ? ' is-featured' : '' ?>"><?= icon(isset(Ext::FEATURED[$id]) ? Ext::FEATURED[$id]['icon'] : 'puzzle') ?></span>
                <div style="flex:1;min-width:0">
                    <b class="zl-strong"><?= e($m['name'] ?? $row['name']) ?></b> <span class="zl-faint">v<?= e($m['version'] ?? $row['version']) ?></span>
                    <?php if ($on): ?><span class="zl-pill is-ok"><?= e(t('ext.on')) ?></span><?php else: ?><span class="zl-pill"><?= e(t('ext.off')) ?></span><?php endif ?>
                    <?php if ($m && $m['license']): ?><span class="zl-pill<?= $row['license_key'] ? ' is-ok' : ' is-warn' ?>"><?= icon('key', 'zl-i-sm') ?> <?= e($row['license_key'] ? t('ext.licensed') : t('ext.needs_key')) ?></span><?php endif ?>
                    <?php if ($x['broken']): ?><span class="zl-pill is-banned"><?= e(t('ext.broken')) ?></span><?php endif ?>
                    <?php if ($m && $m['description'] !== ''): ?><p class="zl-muted" style="font-size:14px;margin-top:4px"><?= e($m['description']) ?></p><?php endif ?>
                    <p class="zl-hint" style="margin-top:4px"><?php if ($m && $m['author'] !== ''): ?><?= e(t('ext.by', ['name' => $m['author']])) ?> · <?php endif ?><?php if ($m && $m['url'] !== ''): ?><a class="zl-link" href="<?= e($m['url']) ?>" target="_blank" rel="noopener noreferrer"><?= e(t('ext.website')) ?></a> · <?php endif ?><?= e(t('ext.installed_on', ['date' => Lang::date((int) $row['installed_at'])])) ?></p>
                </div>
                <div class="zl-row" style="gap:6px;flex-wrap:wrap;justify-content:flex-end">
                    <form method="post" action="<?= e(url('admin/extensions/' . $id . '/' . ($on ? 'disable' : 'enable'))) ?>"><?= csrf_field() ?>
                        <button class="zl-switch-btn<?= $on ? ' is-on' : '' ?>" <?= $x['broken'] ? 'disabled' : '' ?> aria-label="<?= e($on ? t('ext.disable') : t('ext.enable')) ?>" title="<?= e($on ? t('ext.disable') : t('ext.enable')) ?>"><span></span></button>
                    </form>
                    <?php if ($m && ($m['settings'] || $m['license'])): ?><a class="zl-btn zl-btn-soft zl-btn-sm" href="<?= e(url('admin/extensions', $isOpen ? [] : ['ext' => $id])) ?>#ext-<?= e($id) ?>"><?= icon('settings', 'zl-i-sm') ?><?= e(t('ext.settings')) ?></a><?php endif ?>
                    <form method="post" action="<?= e(url('admin/extensions/' . $id . '/delete')) ?>" data-confirm="<?= e(t('ext.remove_confirm', ['name' => $m['name'] ?? $row['name']])) ?>"><?= csrf_field() ?><button class="zl-icon-btn" aria-label="<?= e(t('ext.remove')) ?>" title="<?= e(t('ext.remove')) ?>"><?= icon('trash') ?></button></form>
                </div>
            </div>
            <?php if ($isOpen && $m && ($m['settings'] || $m['license'])): ?>
            <form class="zl-pad zl-stack zl-ext-settings" method="post" action="<?= e(url('admin/extensions/' . $id . '/settings')) ?>">
                <?= csrf_field() ?>
                <?php if ($m['license']): ?>
                <label class="zl-field"><span><?= icon('key', 'zl-i-sm') ?> <?= e($m['license_label'] !== '' ? $m['license_label'] : t('ext.license_key')) ?></span>
                    <input class="zl-input" name="license_key" value="<?= e((string) $row['license_key']) ?>" autocomplete="off" spellcheck="false">
                    <small class="zl-hint"><?= e(t('ext.license_hint')) ?><?php if ($m['license_url'] !== ''): ?> <a class="zl-link" href="<?= e($m['license_url']) ?>" target="_blank" rel="noopener noreferrer"><?= e(t('ext.get_license')) ?></a><?php endif ?></small></label>
                <?php endif ?>
                <?php foreach ($m['settings'] as $fd): $v = $vals[$fd['key']] ?? $fd['default']; $nm = 's[' . $fd['key'] . ']'; ?>
                    <?php if ($fd['type'] === 'toggle'): ?>
                        <label class="zl-check"><span><?= e($fd['label']) ?><?php if ($fd['hint'] !== ''): ?><small><?= e($fd['hint']) ?></small><?php endif ?></span><span class="zl-switch"><input type="checkbox" name="<?= e($nm) ?>" value="1" <?= $v ? 'checked' : '' ?>><span></span></span></label>
                    <?php else: ?>
                        <label class="zl-field"><span><?= e($fd['label']) ?></span>
                        <?php if ($fd['type'] === 'textarea'): ?><textarea class="zl-input" name="<?= e($nm) ?>" rows="4"><?= e((string) $v) ?></textarea>
                        <?php elseif ($fd['type'] === 'select'): ?><select class="zl-input" name="<?= e($nm) ?>"><?php foreach ($fd['options'] as $ok => $ol): $ov = is_int($ok) ? $ol : $ok; ?><option value="<?= e((string) $ov) ?>" <?= (string) $v === (string) $ov ? 'selected' : '' ?>><?= e((string) $ol) ?></option><?php endforeach ?></select>
                        <?php elseif ($fd['type'] === 'password'): ?><input class="zl-input" type="password" name="<?= e($nm) ?>" value="" autocomplete="new-password" placeholder="<?= e((string) $v !== '' ? t('ext.saved_secret') : '') ?>">
                        <?php else: ?><input class="zl-input" type="<?= e($fd['type'] === 'number' ? 'number' : ($fd['type'] === 'url' ? 'url' : ($fd['type'] === 'email' ? 'email' : 'text'))) ?>" name="<?= e($nm) ?>" value="<?= e((string) $v) ?>">
                        <?php endif ?>
                        <?php if ($fd['hint'] !== ''): ?><small class="zl-hint"><?= e($fd['hint']) ?></small><?php endif ?></label>
                    <?php endif ?>
                <?php endforeach ?>
                <div><button class="zl-btn zl-btn-main"><?= icon('check', 'zl-i-sm') ?><?= e(t('common.save')) ?></button></div>
            </form>
            <?php endif ?>
        </section>
        <?php endforeach ?>
    </div>
</div>
