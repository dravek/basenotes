<?php declare(strict_types=1);
/** @var \App\Repos\NoteDto|null $note */
use App\Util\Csrf;
$isNew = $note === null;
$formAction = $isNew ? '/app/notes' : '/app/notes/' . $note->id;
ob_start();
?>
<div class="note-editor">
    <form method="POST" action="<?= e($formAction) ?>" class="note-form" id="note-form">
        <input type="hidden" name="_csrf" value="<?= e(Csrf::token()) ?>">
        <div class="note-form-header">
            <input
                type="text"
                name="title"
                value="<?= e($isNew ? '' : $note->title) ?>"
                placeholder="Note title…"
                class="note-title-input"
                required
            >
            <div class="note-form-actions">
                <button type="submit" class="btn btn-primary">Save</button>
                <a href="/app/notes" class="btn btn-secondary">Cancel</a>
                <?php if (!$isNew): ?>
                <button
                    type="submit"
                    form="delete-form"
                    class="btn btn-danger"
                    onclick="return confirm('Delete this note?')"
                >Delete</button>
                <?php endif; ?>
            </div>
        </div>
        <textarea name="content_md" id="note-content" class="note-content"><?= e($isNew ? '' : $note->contentMd) ?></textarea>
    </form>
    <?php if (!$isNew): ?>
    <form method="POST" action="/app/notes/<?= e($note->id) ?>/delete" id="delete-form">
        <input type="hidden" name="_csrf" value="<?= e(Csrf::token()) ?>">
    </form>
    <?php endif; ?>
</div>
<link rel="stylesheet" href="https://unpkg.com/easymde/dist/easymde.min.css">
<script src="https://unpkg.com/easymde/dist/easymde.min.js"></script>
<?php
$bodyContent = ob_get_clean();
$title = $isNew ? 'New Note' : e($note->title);
require __DIR__ . '/../../views/layout.php';
