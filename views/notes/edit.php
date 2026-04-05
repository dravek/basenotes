<?php declare(strict_types=1);
/** @var \App\Repos\NoteDto|null $note */
/** @var list<string> $tags */
use App\Util\Csrf;
$isNew = $note === null;
$formAction = $isNew ? '/app/notes' : '/app/notes/' . $note->id;
ob_start();
?>
<div class="note-editor">
    <form method="POST" action="<?= e($formAction) ?>" class="note-form" id="note-form">
        <input type="hidden" name="_csrf" value="<?= e(Csrf::token()) ?>">
        <div class="note-form-header">
            <label for="note-title" class="sr-only">Note title</label>
            <input
                id="note-title"
                type="text"
                name="title"
                value="<?= e($isNew ? '' : $note->title) ?>"
                placeholder="Note title…"
                class="note-title-input"
                required
            >
            <div class="note-form-actions">
                <button type="submit" class="btn btn-primary">Save</button>
                <a href="/app/notes" class="btn btn-secondary">Close</a>
                <?php if (!$isNew): ?>
                <a href="/app/notes/<?= e($note->id) ?>/history" class="btn btn-secondary">History</a>
                <a href="/app/notes/<?= e($note->id) ?>/export" class="btn btn-secondary">Export</a>
                <button
                    type="submit"
                    form="delete-form"
                    class="btn btn-danger"
                    onclick="return confirm('Delete this note?')"
                >Delete</button>
                <?php endif; ?>
            </div>
        </div>
        <label for="note-tags">Tags</label>
        <input
            id="note-tags"
            type="text"
            name="tags"
            value="<?= e(implode(', ', $tags ?? [])) ?>"
            placeholder="work, ideas, home"
            class="note-tags-input"
        >
        <label for="note-content" class="sr-only">Note content (Markdown)</label>
        <textarea name="content_md" id="note-content" class="note-content"><?= e($isNew ? '' : $note->contentMd) ?></textarea>
    </form>
    <?php if (!$isNew): ?>
    <form method="POST" action="/app/notes/<?= e($note->id) ?>/delete" id="delete-form">
        <input type="hidden" name="_csrf" value="<?= e(Csrf::token()) ?>">
    </form>
    <?php endif; ?>
</div>
<link
    rel="stylesheet"
    href="https://unpkg.com/easymde/dist/easymde.min.css"
    integrity="sha384-3AvV7152TgYAMYdGZPqG9BpmSH2ZW6ewTDL0QV5PyNkl19KMI+yLMdJz183N8A2d"
    crossorigin="anonymous"
>
<script
    src="https://unpkg.com/easymde/dist/easymde.min.js"
    integrity="sha384-YDXeUfPZ4SP6vJpnF+ZMmf4B1bax6yd4Q/aNbkvLidRD843hPG5RE67M0IYT4LOq"
    crossorigin="anonymous"
></script>
<?php
$bodyContent = ob_get_clean();
$title = $isNew ? 'New Note' : $note->title;
require __DIR__ . '/../../views/layout.php';
