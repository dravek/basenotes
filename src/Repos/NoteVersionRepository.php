<?php

declare(strict_types=1);

namespace App\Repos;

final readonly class NoteVersionDto
{
    public function __construct(
        public string $id,
        public string $noteId,
        public string $userId,
        public int $versionNo,
        public string $title,
        public string $contentMd,
        public int $sourceUpdatedAt,
        public int $createdAt,
        public string $eventType,
    ) {}
}

final class NoteVersionRepository
{
    public function __construct(private \PDO $pdo) {}

    public function createSnapshot(NoteDto $note, string $eventType, int $createdAt): void
    {
        $stmt = $this->pdo->prepare(
            'INSERT INTO note_versions (
                 id, note_id, user_id, version_no, title, content_md,
                 source_updated_at, created_at, event_type
             )
             SELECT
                 :id,
                 :note_id,
                 :user_id,
                 COALESCE(MAX(version_no), 0) + 1,
                 :title,
                 :content_md,
                 :source_updated_at,
                 :created_at,
                 :event_type
             FROM note_versions
             WHERE note_id = :note_id_for_max AND user_id = :user_id_for_max'
        );

        $stmt->execute([
            'id'              => \App\Util\Id::ulid(),
            'note_id'         => $note->id,
            'user_id'         => $note->userId,
            'title'           => $note->title,
            'content_md'      => $note->contentMd,
            'source_updated_at' => $note->updatedAt,
            'created_at'      => $createdAt,
            'event_type'      => $eventType,
            'note_id_for_max' => $note->id,
            'user_id_for_max' => $note->userId,
        ]);
    }

    /** @return list<NoteVersionDto> */
    public function listByNote(string $noteId, string $userId, int $limit = 100): array
    {
        $stmt = $this->pdo->prepare(
            'SELECT id, note_id, user_id, version_no, title, content_md, source_updated_at, created_at, event_type
             FROM note_versions
             WHERE note_id = :note_id AND user_id = :user_id
             ORDER BY version_no DESC, id DESC
             LIMIT :limit'
        );
        $stmt->bindValue(':note_id', $noteId);
        $stmt->bindValue(':user_id', $userId);
        $stmt->bindValue(':limit', $limit, \PDO::PARAM_INT);
        $stmt->execute();

        return array_map($this->rowToDto(...), $stmt->fetchAll());
    }

    public function findById(string $id, string $noteId, string $userId): NoteVersionDto|null
    {
        $stmt = $this->pdo->prepare(
            'SELECT id, note_id, user_id, version_no, title, content_md, source_updated_at, created_at, event_type
             FROM note_versions
             WHERE id = :id AND note_id = :note_id AND user_id = :user_id'
        );
        $stmt->execute([
            'id' => $id,
            'note_id' => $noteId,
            'user_id' => $userId,
        ]);

        $row = $stmt->fetch();
        if ($row === false) {
            return null;
        }

        return $this->rowToDto($row);
    }

    private function rowToDto(array $row): NoteVersionDto
    {
        return new NoteVersionDto(
            id: $row['id'],
            noteId: $row['note_id'],
            userId: $row['user_id'],
            versionNo: (int)$row['version_no'],
            title: $row['title'],
            contentMd: $row['content_md'],
            sourceUpdatedAt: (int)$row['source_updated_at'],
            createdAt: (int)$row['created_at'],
            eventType: $row['event_type'],
        );
    }
}

