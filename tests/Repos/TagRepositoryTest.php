<?php

declare(strict_types=1);

namespace App\Tests\Repos;

use App\Repos\TagRepository;
use PHPUnit\Framework\TestCase;

final class TagRepositoryTest extends TestCase
{
    public function testFindOrCreateNormalizesSlugAndReusesExisting(): void
    {
        $pdo = new \PDO('sqlite::memory:');
        $pdo->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION);
        $pdo->setAttribute(\PDO::ATTR_DEFAULT_FETCH_MODE, \PDO::FETCH_ASSOC);
        $pdo->exec('CREATE TABLE tags (id TEXT PRIMARY KEY, name TEXT NOT NULL, slug TEXT NOT NULL, created_at INTEGER NOT NULL, updated_at INTEGER NOT NULL)');
        $pdo->exec('CREATE UNIQUE INDEX idx_tags_slug ON tags (slug)');
        $pdo->exec('CREATE TABLE note_tags (note_id TEXT NOT NULL, tag_id TEXT NOT NULL, created_at INTEGER NOT NULL, PRIMARY KEY (note_id, tag_id))');

        $repo = new TagRepository($pdo);

        $first = $repo->findOrCreate('Work Stuff');
        $second = $repo->findOrCreate('work stuff');

        $this->assertSame($first->id, $second->id);
        $this->assertSame('Work Stuff', $first->name);
        $this->assertSame('work-stuff', $first->slug);
    }

    public function testSyncForNoteReplacesTags(): void
    {
        $pdo = new \PDO('sqlite::memory:');
        $pdo->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION);
        $pdo->setAttribute(\PDO::ATTR_DEFAULT_FETCH_MODE, \PDO::FETCH_ASSOC);
        $pdo->exec('CREATE TABLE tags (id TEXT PRIMARY KEY, name TEXT NOT NULL, slug TEXT NOT NULL, created_at INTEGER NOT NULL, updated_at INTEGER NOT NULL)');
        $pdo->exec('CREATE UNIQUE INDEX idx_tags_slug ON tags (slug)');
        $pdo->exec('CREATE TABLE note_tags (note_id TEXT NOT NULL, tag_id TEXT NOT NULL, created_at INTEGER NOT NULL, PRIMARY KEY (note_id, tag_id))');

        $repo = new TagRepository($pdo);
        $repo->syncForNote('note-1', ['Work', 'Home']);
        $repo->syncForNote('note-1', ['Work']);

        $stmt = $pdo->query('SELECT t.slug FROM note_tags nt INNER JOIN tags t ON t.id = nt.tag_id WHERE nt.note_id = "note-1" ORDER BY t.slug');
        $slugs = array_map(static fn(array $row): string => $row['slug'], $stmt->fetchAll());

        $this->assertSame(['work'], $slugs);
    }
}
