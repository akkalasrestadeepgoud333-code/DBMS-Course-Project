<?php
/** Renders the tracking timeline. Expects $history (rows from tracking_history, newest first). */
?>
<?php if (!$history): ?>
    <div class="empty-state"><i class="bi bi-clock-history"></i>No tracking events recorded yet.</div>
<?php else: ?>
    <ul class="timeline">
        <?php foreach ($history as $i => $h):
            $cls = $i === 0 ? 'latest' : '';
            if ($i === 0 && $h['status'] === 'Delivered') $cls .= ' is-delivered';
            if ($i === 0 && in_array($h['status'], ['Failed', 'Returned'], true)) $cls .= ' is-failed';
        ?>
            <li class="<?= $cls ?>">
                <span class="dot"><i class="bi bi-<?= status_icon($h['status']) ?>"></i></span>
                <div class="d-flex flex-wrap justify-content-between gap-1">
                    <span class="t-title"><?= e($h['status']) ?></span>
                    <span class="t-meta"><?= e(fmt_date($h['created_at'], true)) ?></span>
                </div>
                <div class="t-meta"><i class="bi bi-geo-alt"></i> <?= e($h['location']) ?></div>
                <?php if ($h['remarks'] !== null && $h['remarks'] !== ''): ?><div class="t-remark"><?= e($h['remarks']) ?></div><?php endif; ?>
                <?php if (!empty($showUpdatedBy)): ?><div class="t-meta small">Updated by: <?= e($h['updated_by_name'] ?? 'System') ?><?= !empty($h['updated_by_role']) ? ' (' . e($h['updated_by_role']) . ')' : '' ?></div><?php endif; ?>
            </li>
        <?php endforeach; ?>
    </ul>
<?php endif; ?>
