<?php

declare(strict_types=1);

namespace App\Tests\Repos;

use App\Repos\TagRepository;
use PHPUnit\Framework\TestCase;

final class TagRepositoryTest extends TestCase
{
    public function testFindOrCreateReusesSlugAndSyncsNoteTags(): void
    {
        $pdo = $this->pdo();
        $this->migrate($pdo);

        $repo = new TagRepository($pdo);
        $first = $repo->findOrCreate('Work Stuff');
        $second = $repo->findOrCreate('work stuff');

        $this->assertSame($first->id, $second->id);
        $this->assertSame('work-stuff', $first->slug);

        $repo->syncForNote('n1', ['Work Stuff', 'Home']);
        $tags = $repo->listForNote('n1');
        $this->assertCount(2, $tags);
        $this->assertSame(['Home', 'Work Stuff'], array_map(static fn($tag) => $tag->name, $tags));
    }

    private function pdo(): \PDO
    {
        $pdo = new \PDO('sqlite::memory:');
        $pdo->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION);
        $pdo->setAttribute(\PDO::ATTR_DEFAULT_FETCH_MODE, \PDO::FETCH_ASSOC);
        return $pdo;
    }

    private function migrate(\PDO $pdo): void
    {
        $pdo->exec('CREATE TABLE tags (id TEXT PRIMARY KEY, name TEXT NOT NULL, slug TEXT NOT NULL, created_at INTEGER NOT NULL, updated_at INTEGER NOT NULL)');
        $pdo->exec('CREATE UNIQUE INDEX idx_tags_slug ON tags (slug)');
        $pdo->exec('CREATE TABLE note_tags (note_id TEXT NOT NULL, tag_id TEXT NOT NULL, created_at INTEGER NOT NULL, PRIMARY KEY (note_id, tag_id))');
    }
}
