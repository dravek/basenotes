<?php
<?php
/**
 * Copyright (c) 2026 David Carrillo <dravek@gmail.com>
 *
 * Permission is hereby granted, free of charge, to any person obtaining a copy
 * of this software and associated documentation files (the "Software"), to deal
 * in the Software without restriction, including without limitation the rights
 * to use, copy, modify, merge, publish, distribute, sublicense, and/or sell
 * copies of the Software, and to permit persons to whom the Software is
 * furnished to do so, subject to the following conditions:
 *
 * The above copyright notice and this permission notice shall be included in all
 * copies or substantial portions of the Software.
 *
 * THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR
 * IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY,
 * FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL THE
 * AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER
 * LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM,
 * OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN THE
 * SOFTWARE.
 */

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
    public function listByUser(string $userId, ?string $search = null, ?string $tagSlug = null): array
    {
        $where = [
            'n.user_id = :user_id',
            'n.deleted_at IS NULL',
        ];
        $params = ['user_id' => $userId];

        if ($search !== null) {
            $where[] = '(n.title ILIKE :q OR n.content_md ILIKE :q OR EXISTS (SELECT 1 FROM note_tags nt INNER JOIN tags t ON t.id = nt.tag_id WHERE nt.note_id = n.id AND (t.name ILIKE :q OR t.slug ILIKE :q)))';
            $params['q'] = '%' . $search . '%';
        }

        if ($tagSlug !== null && $tagSlug !== '') {
            $where[] = 'EXISTS (SELECT 1 FROM note_tags nt INNER JOIN tags t ON t.id = nt.tag_id WHERE nt.note_id = n.id AND t.slug = :tag_slug)';
            $params['tag_slug'] = $tagSlug;
        }

        $sql = 'SELECT n.id, n.user_id, n.title, n.content_md, n.created_at, n.updated_at, n.deleted_at FROM notes n WHERE ' . implode(' AND ', $where) . ' ORDER BY n.updated_at DESC';
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);

        return array_map($this->rowToDto(...), $stmt->fetchAll());
    }

    public function findById(string $id, string $userId): NoteDto|null
    {
        $stmt = $this->pdo->prepare(
            'SELECT n.id, n.user_id, n.title, n.content_md, n.created_at, n.updated_at, n.deleted_at
             FROM notes n
             WHERE n.id = :id AND n.user_id = :user_id AND n.deleted_at IS NULL'
        );
        $stmt->execute(['id' => $id, 'user_id' => $userId]);
        $row = $stmt->fetch();
        if ($row === false) {
            return null;
        }
        return $this->rowToDto($row);
    }

    public function findByIdIncludingDeleted(string $id, string $userId): NoteDto|null
    {
        $stmt = $this->pdo->prepare(
            'SELECT n.id, n.user_id, n.title, n.content_md, n.created_at, n.updated_at, n.deleted_at
             FROM notes n
             WHERE n.id = :id AND n.user_id = :user_id'
        );
        $stmt->execute(['id' => $id, 'user_id' => $userId]);
        $row = $stmt->fetch();
        if ($row === false) {
            return null;
        }
        return $this->rowToDto($row);
    }

    public function findByIdForUpdate(string $id, string $userId): NoteDto|null
    {
        $stmt = $this->pdo->prepare(
            'SELECT n.id, n.user_id, n.title, n.content_md, n.created_at, n.updated_at, n.deleted_at
             FROM notes n
             WHERE n.id = :id AND n.user_id = :user_id AND deleted_at IS NULL
             FOR UPDATE'
        );
        $stmt->execute(['id' => $id, 'user_id' => $userId]);
        $row = $stmt->fetch();
        if ($row === false) {
            return null;
        }
        return $this->rowToDto($row);
    }

    public function findByIdForUpdateIncludingDeleted(string $id, string $userId): NoteDto|null
    {
        $stmt = $this->pdo->prepare(
            'SELECT n.id, n.user_id, n.title, n.content_md, n.created_at, n.updated_at, n.deleted_at
             FROM notes n
             WHERE n.id = :id AND n.user_id = :user_id
             FOR UPDATE'
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
            'deleted_at' => \App\Util\Clock::now(),
            'id'         => $id,
            'user_id'    => $userId,
        ]);
    }

    public function restore(NoteDto $dto): void
    {
        $stmt = $this->pdo->prepare(
            'UPDATE notes
             SET title = :title, content_md = :content_md, updated_at = :updated_at, deleted_at = NULL
             WHERE id = :id AND user_id = :user_id'
        );
        $stmt->execute([
            'title'      => $dto->title,
            'content_md' => $dto->contentMd,
            'updated_at' => $dto->updatedAt,
            'id'         => $dto->id,
            'user_id'    => $dto->userId,
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
                'SELECT n.id, n.user_id, n.title, n.content_md, n.created_at, n.updated_at, n.deleted_at
                 FROM notes n
                 WHERE n.user_id = :user_id
                   AND n.deleted_at IS NULL
                   AND (n.updated_at < :cursor_updated_at
                        OR (n.updated_at = :cursor_updated_at AND n.id < :cursor_id))
                 ORDER BY n.updated_at DESC, n.id DESC
                 LIMIT :limit'
            );
            $stmt->bindValue(':user_id', $userId);
            $stmt->bindValue(':cursor_updated_at', $cursor['updated_at'], \PDO::PARAM_INT);
            $stmt->bindValue(':cursor_id', $cursor['id']);
            $stmt->bindValue(':limit', $perPage + 1, \PDO::PARAM_INT);
            $stmt->execute();
        } else {
            $stmt = $this->pdo->prepare(
                'SELECT n.id, n.user_id, n.title, n.content_md, n.created_at, n.updated_at, n.deleted_at
                 FROM notes n
                 WHERE n.user_id = :user_id
                   AND n.deleted_at IS NULL
                 ORDER BY n.updated_at DESC, n.id DESC
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
