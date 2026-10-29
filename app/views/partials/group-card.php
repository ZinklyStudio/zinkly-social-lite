<a class="zl-card zl-group-card" href="<?= e(url('g/' . $g['id'])) ?>">
    <div class="zl-group-cover"><?php if ($g['cover']): ?><img src="<?= e(upload_url($g['cover'])) ?>" alt="" loading="lazy"><?php endif ?></div>
    <div class="zl-pad">
        <div class="zl-row" style="align-items:flex-start">
            <?php if (!empty($g['avatar'])): ?><span class="zl-av zl-av-md zl-av-group zl-card-av"><img src="<?= e(upload_url($g['avatar'])) ?>" alt="" loading="lazy"></span><?php else: ?><span class="zl-av zl-av-md zl-av-group zl-card-av" aria-hidden="true"><?= e(initials($g['name'])) ?></span><?php endif ?>
            <div style="min-width:0">
                <b class="zl-strong"><?= e($g['name']) ?></b>
                <p class="zl-faint"><?= icon($g['privacy'] === 'private' ? 'lock' : 'globe', 'zl-i-sm') ?> <?= e(t('group.' . $g['privacy'])) ?> · <?= e(tc('group.n_members', (int) $g['members_count'])) ?><?php if (($g['category'] ?? '') !== ''): ?> · <?= e(t('group.cat_' . $g['category'])) ?><?php endif ?></p>
            </div>
        </div>
        <?php if ($g['description'] !== ''): ?><p class="zl-muted" style="font-size:14px;margin-top:8px"><?= e(str_limit($g['description'], 110)) ?></p><?php endif ?>
    </div>
</a>
