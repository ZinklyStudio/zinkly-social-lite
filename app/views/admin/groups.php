<div class="zl-settings zl-admin">
    <?php View::partial('admin-nav', ['anav' => $anav]); ?>
    <div class="zl-stack">
        <div class="zl-card zl-pad"><h1 class="zl-h1"><?= e(t('admin.groups')) ?> <span class="zl-badge"><?= count($groups) ?></span></h1><p class="zl-faint"><?= e(t('admin.groups_hint')) ?></p></div>
        <div class="zl-card zl-table-wrap"><table class="zl-table">
            <thead><tr><th><?= e(t('group.name')) ?></th><th><?= e(t('admin.privacy')) ?></th><th><?= e(t('group.category')) ?></th><th><?= e(t('admin.members')) ?></th><th><?= e(t('admin.rules_col')) ?></th><th><span class="sr-only"><?= e(t('admin.actions')) ?></span></th></tr></thead>
            <tbody><?php foreach ($groups as $g): ?><tr>
                <td><a class="zl-strong" href="<?= e(url('g/' . $g['id'])) ?>"><?= e($g['name']) ?></a><br><small class="zl-faint"><?= e(Lang::date((int) $g['created_at'])) ?></small></td>
                <td><?= icon($g['privacy'] === 'private' ? 'lock' : 'globe', 'zl-i-sm') ?> <?= e(t('group.' . $g['privacy'])) ?><?php if ($g['visibility'] === 'hidden'): ?> <span class="zl-pill"><?= e(t('group.hidden')) ?></span><?php endif ?></td>
                <td><?= $g['category'] !== '' ? e(t('group.cat_' . $g['category'])) : '—' ?></td>
                <td><?= (int) $g['members_count'] ?></td>
                <td class="zl-faint"><?php $flags = array_filter([$g['post_permission'] === 'admins' ? t('group.perm_admins') : '', $g['post_approval'] ? t('group.approval') : '', $g['allow_anonymous'] ? t('group.anon_on') : '']); echo $flags ? e(implode(' · ', $flags)) : '—'; ?></td>
                <td><form method="post" action="<?= e(url('g/' . $g['id'] . '/delete')) ?>" data-confirm="<?= e(t('group.delete_confirm')) ?>"><?= csrf_field() ?><input type="hidden" name="from" value="admin"><button class="zl-btn zl-btn-danger zl-btn-sm"><?= icon('trash', 'zl-i-sm') ?><?= e(t('group.delete')) ?></button></form></td>
            </tr><?php endforeach ?>
            <?php if (!$groups): ?><tr><td colspan="6" class="zl-faint"><?= e(t('group.none')) ?></td></tr><?php endif ?></tbody>
        </table></div>
    </div>
</div>
