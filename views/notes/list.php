<?php declare(strict_types=1);
/** @var list<\App\Repos\NoteDto> $notes */
/** @var string $search */
use App\Util\Csrf;
$search ??= '';
ob_start();
?>
<div class="notes-list">
    <div class="notes-list-header">
        <h1>My Notes</h1>
        <a href="/app/notes/new" class="btn btn-primary">+ New Note</a>
    </div>
    <form method="GET" action="/app/notes" class="search-form">
        <input type="search" name="q" value="<?= e($search) ?>" placeholder="Search notes…" aria-label="Search">
        <button type="submit">Search</button>
        <?php if ($search !== ''): ?>
        <a href="/app/notes">Clear</a>
        <?php endif; ?>
    </form>
    <?php if ($search !== ''): ?>
    <p class="search-meta">Results for: <strong><?= e($search) ?></strong></p>
    <?php endif; ?>
    <?php if (count($notes) === 0): ?>
    <p class="empty-state">
        <?= $search !== '' ? 'No notes match your search.' : 'No notes yet. <a href="/app/notes/new">Create your first note</a>.' ?>
    </p>
    <?php else: ?>
    <ul class="notes-items">
        <?php foreach ($notes as $note): ?>
        <li class="note-item">
            <a href="/app/notes/<?= e($note->id) ?>" class="note-link">
                <span class="note-title"><?= e($note->title) ?></span>
                <span class="note-preview"><?= e(mb_strimwidth(strip_tags($note->contentMd), 0, 120, '…')) ?></span>
                <span class="note-date"><?= e(date('d M Y', $note->updatedAt)) ?></span>
            </a>
        </li>
        <?php endforeach; ?>
    </ul>
    <?php endif; ?>
</div>
<?php
$bodyContent = ob_get_clean();
$title = 'My Notes';
require __DIR__ . '/../../views/layout.php';
