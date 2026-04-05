<?php declare(strict_types=1);
/** @var list<array<string, mixed>> $auditRows */
ob_start();
?>
<div class="settings-box">
    <h1>Audit Log</h1>
    <p>Recent security and account events.</p>
    <?php if (count($auditRows) === 0): ?>
    <p>No audit entries found.</p>
    <?php else: ?>
    <table class="tokens-table">
        <thead>
            <tr>
                <th>When</th>
                <th>Event</th>
                <th>Actor</th>
                <th>Target</th>
                <th>Entity</th>
                <th>IP</th>
            </tr>
        </thead>
        <tbody>
        <?php foreach ($auditRows as $row): ?>
        <tr>
            <td><?= e(date('d M Y H:i', (int)$row['created_at'])) ?></td>
            <td><?= e((string)$row['event']) ?></td>
            <td><?= e((string)($row['actor_email'] ?? 'System')) ?></td>
            <td><?= e((string)($row['target_email'] ?? '—')) ?></td>
            <td><?= e((string)($row['entity_type'] ?? '—')) ?><?= ($row['entity_id'] ?? null) !== null ? ' / ' . e((string)$row['entity_id']) : '' ?></td>
            <td><?= e((string)$row['ip']) ?></td>
        </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
    <?php endif; ?>
</div>
<?php
$bodyContent = ob_get_clean();
$title = 'Audit Log';
require __DIR__ . '/../layout.php';
