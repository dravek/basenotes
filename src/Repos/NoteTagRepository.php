<?php

declare(strict_types=1);

namespace App\Repos;

final class NoteTagRepository
{
    public function __construct(private \PDO $pdo) {}

    /** @return list<string> */
    public function listTagNamesForNote(string $noteId): array
    {
        $stmt = $this->pdo->prepare(
            'SELECT t.name
             FROM tags t
             INNER JOIN note_tags nt ON nt.tag_id = t.id
             WHERE nt.note_id = :note_id
             ORDER BY t.name ASC'
        );
        $stmt->execute(['note_id' => $noteId]);
        return array_map(static fn(array $row): string => $row['name'], $stmt->fetchAll());
    }
}
