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

use App\Util\Id;
use App\Util\Slug;

final readonly class TagDto
{
    public function __construct(
        public string $id,
        public string $name,
        public string $slug,
        public int $createdAt,
        public int $updatedAt,
    ) {}
}

final class TagRepository
{
    public function __construct(private \PDO $pdo) {}

    public function findOrCreate(string $name): TagDto
    {
        $cleanName = trim($name);
        $slug = Slug::from($cleanName);
        if ($cleanName === '' || $slug === '') {
            throw new \InvalidArgumentException('Tag name is invalid.');
        }

        $existing = $this->findBySlug($slug);
        if ($existing !== null) {
            return $existing;
        }

        $now = \App\Util\Clock::now();
        $id = Id::ulid();
        $stmt = $this->pdo->prepare(
            'INSERT INTO tags (id, name, slug, created_at, updated_at)
             VALUES (:id, :name, :slug, :created_at, :updated_at)'
        );
        $stmt->execute([
            'id' => $id,
            'name' => $cleanName,
            'slug' => $slug,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        return new TagDto($id, $cleanName, $slug, $now, $now);
    }

    public function findBySlug(string $slug): TagDto|null
    {
        $stmt = $this->pdo->prepare(
            'SELECT id, name, slug, created_at, updated_at FROM tags WHERE slug = :slug'
        );
        $stmt->execute(['slug' => $slug]);
        $row = $stmt->fetch();
        if ($row === false) {
            return null;
        }
        return $this->rowToDto($row);
    }

    /** @return list<TagDto> */
    public function listForNote(string $noteId): array
    {
        $stmt = $this->pdo->prepare(
            'SELECT t.id, t.name, t.slug, t.created_at, t.updated_at
             FROM tags t
             INNER JOIN note_tags nt ON nt.tag_id = t.id
             WHERE nt.note_id = :note_id
             ORDER BY t.name ASC'
        );
        $stmt->execute(['note_id' => $noteId]);
        return array_map($this->rowToDto(...), $stmt->fetchAll());
    }

    /** @param list<string> $tagNames */
    public function syncForNote(string $noteId, array $tagNames): void
    {
        $tags = [];
        foreach ($tagNames as $tagName) {
            $tagName = trim($tagName);
            if ($tagName === '') {
                continue;
            }
            $slug = Slug::from($tagName);
            if ($slug === '') {
                continue;
            }
            $tags[$slug] = $tagName;
        }

        $stmt = $this->pdo->prepare('DELETE FROM note_tags WHERE note_id = :note_id');
        $stmt->execute(['note_id' => $noteId]);

        if ($tags === []) {
            return;
        }

        $insert = $this->pdo->prepare(
            'INSERT INTO note_tags (note_id, tag_id, created_at) VALUES (:note_id, :tag_id, :created_at)'
        );
        foreach ($tags as $tagName) {
            $tag = $this->findOrCreate($tagName);
            $insert->execute([
                'note_id' => $noteId,
                'tag_id' => $tag->id,
                'created_at' => \App\Util\Clock::now(),
            ]);
        }
    }

    private function rowToDto(array $row): TagDto
    {
        return new TagDto(
            id: $row['id'],
            name: $row['name'],
            slug: $row['slug'],
            createdAt: (int)$row['created_at'],
            updatedAt: (int)$row['updated_at'],
        );
    }
}
