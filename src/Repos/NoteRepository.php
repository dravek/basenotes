<?php

declare(strict_types=1);

namespace App\Repos;

final readonly class NoteDto
{
    public function __construct(
        public string  $id,
        public string  $userId,
        public string  $title,
        public string  $contentMd,
        public int     $createdAt,
        public int     $updatedAt,
        public int|null $deletedAt,
    ) {}
}

final class NoteRepository
{
    public function __construct(private \PDO $pdo) {}

    /** @return list<NoteDto> */
    public function listByUser(string $userId, ?string $search = null): array
    {
        if ($search !== null) {
            $stmt = $this->pdo->prepare(
                'SELECT id, user_id, title, content_md, created_at, updated_at, deleted_at
                 FROM notes
                 WHERE user_id = :user_id
                   AND deleted_at IS NULL
                   AND (title LIKE :q OR content_md LIKE :q)
                 ORDER BY updated_at DESC'
            );
            $stmt->execute(['user_id' => $userId, 'q' => '%' . $search . '%']);
        } else {
            $stmt = $this->pdo->prepare(
                'SELECT id, user_id, title, content_md, created_at, updated_at, deleted_at
                 FROM notes
                 WHERE user_id = :user_id
                   AND deleted_at IS NULL
                 ORDER BY updated_at DESC'
            );
            $stmt->execute(['user_id' => $userId]);
        }

        return array_map($this->rowToDto(...), $stmt->fetchAll());
    }

    public function findById(string $id, string $userId): NoteDto|null
    {
        $stmt = $this->pdo->prepare(
            'SELECT id, user_id, title, content_md, created_at, updated_at, deleted_at
             FROM notes
             WHERE id = :id AND user_id = :user_id AND deleted_at IS NULL'
        );
        $stmt->execute(['id' => $id, 'user_id' => $userId]);
        $row = $stmt->fetch();
        if ($row === false) {
            return null;
        }
        return $this->rowToDto($row);
    }

    public function create(NoteDto $dto): void
    {
        $stmt = $this->pdo->prepare(
            'INSERT INTO notes (id, user_id, title, content_md, created_at, updated_at, deleted_at)
             VALUES (:id, :user_id, :title, :content_md, :created_at, :updated_at, :deleted_at)'
        );
        $stmt->execute([
            'id'         => $dto->id,
            'user_id'    => $dto->userId,
            'title'      => $dto->title,
            'content_md' => $dto->contentMd,
            'created_at' => $dto->createdAt,
            'updated_at' => $dto->updatedAt,
            'deleted_at' => $dto->deletedAt,
        ]);
    }

    public function update(NoteDto $dto): void
    {
        $stmt = $this->pdo->prepare(
            'UPDATE notes
             SET title = :title, content_md = :content_md, updated_at = :updated_at
             WHERE id = :id AND user_id = :user_id AND deleted_at IS NULL'
        );
        $stmt->execute([
            'title'      => $dto->title,
            'content_md' => $dto->contentMd,
            'updated_at' => $dto->updatedAt,
            'id'         => $dto->id,
            'user_id'    => $dto->userId,
        ]);
    }

    public function softDelete(string $id, string $userId): void
    {
        $stmt = $this->pdo->prepare(
            'UPDATE notes SET deleted_at = :deleted_at
             WHERE id = :id AND user_id = :user_id AND deleted_at IS NULL'
        );
        $stmt->execute([
            'deleted_at' => time(),
            'id'         => $id,
            'user_id'    => $userId,
        ]);
    }

    /**
     * Cursor-paginated list for the API.
     * Returns [list<NoteDto>, string|null $nextCursor].
     *
     * @return array{list<NoteDto>, string|null}
     */
    public function listPaginated(
        string $userId,
        ?array $cursor,
        int $perPage,
    ): array {
        if ($cursor !== null) {
            $stmt = $this->pdo->prepare(
                'SELECT id, user_id, title, content_md, created_at, updated_at, deleted_at
                 FROM notes
                 WHERE user_id = :user_id
                   AND deleted_at IS NULL
                   AND (updated_at < :cursor_updated_at
                        OR (updated_at = :cursor_updated_at AND id < :cursor_id))
                 ORDER BY updated_at DESC, id DESC
                 LIMIT :limit'
            );
            $stmt->bindValue(':user_id', $userId);
            $stmt->bindValue(':cursor_updated_at', $cursor['updated_at'], \PDO::PARAM_INT);
            $stmt->bindValue(':cursor_id', $cursor['id']);
            $stmt->bindValue(':limit', $perPage + 1, \PDO::PARAM_INT);
            $stmt->execute();
        } else {
            $stmt = $this->pdo->prepare(
                'SELECT id, user_id, title, content_md, created_at, updated_at, deleted_at
                 FROM notes
                 WHERE user_id = :user_id
                   AND deleted_at IS NULL
                 ORDER BY updated_at DESC, id DESC
                 LIMIT :limit'
            );
            $stmt->bindValue(':user_id', $userId);
            $stmt->bindValue(':limit', $perPage + 1, \PDO::PARAM_INT);
            $stmt->execute();
        }

        $rows  = $stmt->fetchAll();
        $hasMore = count($rows) > $perPage;
        if ($hasMore) {
            array_pop($rows);
        }
        $notes = array_map($this->rowToDto(...), $rows);

        $nextCursor = null;
        if ($hasMore && count($notes) > 0) {
            $last       = end($notes);
            $nextCursor = base64_encode($last->updatedAt . ':' . $last->id);
        }

        return [$notes, $nextCursor];
    }

    private function rowToDto(array $row): NoteDto
    {
        return new NoteDto(
            id:        $row['id'],
            userId:    $row['user_id'],
            title:     $row['title'],
            contentMd: $row['content_md'],
            createdAt: (int)$row['created_at'],
            updatedAt: (int)$row['updated_at'],
            deletedAt: $row['deleted_at'] !== null ? (int)$row['deleted_at'] : null,
        );
    }
}
