<?php declare(strict_types=1);
/** @var \App\Repos\NoteDto $note */
/** @var list<\App\Repos\NoteVersionDto> $versions */
use App\Util\Csrf;

ob_start();
?>
<div class="notes-list">
    <div class="notes-list-header">
        <div>
            <h2 style="margin: 0;">History: <?= e($note->title) ?></h2>
            <p style="margin: 0.35rem 0 0; opacity: 0.8;">Snapshots saved before updates, deletes, and rollbacks.</p>
            <?php if ($note->deletedAt !== null): ?>
            <p style="margin: 0.35rem 0 0; color: #b42318;">This note is deleted. Roll back a version to restore it.</p>
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
                <li class="note-item" style="display: block;">
                    <div style="display: flex; justify-content: space-between; gap: 1rem; align-items: baseline;">
                        <h3 style="margin: 0;">Version <?= e((string)$version->versionNo) ?></h3>
                        <small>
                            <?= e(date('Y-m-d H:i:s', $version->createdAt)) ?>
                            (<?= e($version->eventType) ?>)
                        </small>
                    </div>
                    <p style="margin: 0.4rem 0 0.25rem;"><strong><?= e($version->title !== '' ? $version->title : 'Untitled') ?></strong></p>
                    <p style="margin: 0; white-space: pre-wrap;">
                        <?= e($preview === '' ? '(empty note)' : mb_strimwidth($preview, 0, 240, '...')) ?>
                    </p>
                    <p style="margin: 0.5rem 0 0; opacity: 0.75;">
                        Original note timestamp: <?= e(date('Y-m-d H:i:s', $version->sourceUpdatedAt)) ?>
                    </p>
                    <form
                        method="POST"
                        action="/app/notes/<?= e($note->id) ?>/history/<?= e($version->id) ?>/rollback"
                        style="margin-top: 0.75rem;"
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
