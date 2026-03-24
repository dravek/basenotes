<?php declare(strict_types=1);
/** @var \App\Repos\NoteDto $note */
/** @var list<\App\Repos\NoteVersionDto> $versions */
use App\Util\Csrf;

ob_start();
?>
<div class="notes-list">
    <div class="notes-list-header">
        <div>
            <h1>History: <?= e($note->title) ?></h1>
            <p class="history-meta">Snapshots saved before updates, deletes, and rollbacks.</p>
            <?php if ($note->deletedAt !== null): ?>
            <p class="history-meta">This note is deleted. Roll back a version to restore it.</p>
            <?php endif; ?>
        </div>
        <div class="note-form-actions">
            <?php if ($note->deletedAt === null): ?>
            <a href="/app/notes/<?= e($note->id) ?>" class="btn btn-primary">Back to Note</a>
            <?php endif; ?>
            <a href="/app/notes" class="btn btn-secondary">All Notes</a>
        </div>
    </div>

    <?php if ($versions === []): ?>
        <p>No saved versions yet.</p>
    <?php else: ?>
        <ul class="notes-items">
            <?php foreach ($versions as $version): ?>
                <?php $preview = trim($version->contentMd); ?>
                <li class="note-item history-note-item">
                    <div class="history-row">
                        <h3>Version <?= e((string)$version->versionNo) ?></h3>
                        <small>
                            <?= e(date('Y-m-d H:i:s', $version->createdAt)) ?>
                            (<?= e($version->eventType) ?>)
                        </small>
                    </div>
                    <p class="history-title"><strong><?= e($version->title !== '' ? $version->title : 'Untitled') ?></strong></p>
                    <p class="history-preview">
                        <?= e($preview === '' ? '(empty note)' : mb_strimwidth($preview, 0, 240, '...')) ?>
                    </p>
                    <p class="history-meta">
                        Original note timestamp: <?= e(date('Y-m-d H:i:s', $version->sourceUpdatedAt)) ?>
                    </p>
                    <form
                        method="POST"
                        action="/app/notes/<?= e($note->id) ?>/history/<?= e($version->id) ?>/rollback"
                        class="history-form"
                        onsubmit="return confirm('Rollback note to version <?= e((string)$version->versionNo) ?>? Current content will be saved to history first.');"
                    >
                        <input type="hidden" name="_csrf" value="<?= e(Csrf::token()) ?>">
                        <button type="submit" class="btn btn-secondary">
                            <?= $note->deletedAt !== null ? 'Restore to This Version' : 'Rollback to This Version' ?>
                        </button>
                    </form>
                </li>
            <?php endforeach; ?>
        </ul>
    <?php endif; ?>
</div>
<?php
$bodyContent = ob_get_clean();
$title = 'Note History';
require __DIR__ . '/../../views/layout.php';
