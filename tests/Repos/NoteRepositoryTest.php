<?php

declare(strict_types=1);

namespace App\Tests\Repos;

use App\Repos\NoteDto;
use App\Repos\NoteRepository;
use PHPUnit\Framework\TestCase;

final class NoteRepositoryTest extends TestCase
{
    public function testListByUserCanFilterByTagSlug(): void
    {
        $pdo = new \PDO('sqlite::memory:');
        $pdo->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION);
        $pdo->setAttribute(\PDO::ATTR_DEFAULT_FETCH_MODE, \PDO::FETCH_ASSOC);
        $pdo->exec('CREATE TABLE notes (id TEXT PRIMARY KEY, user_id TEXT NOT NULL, title TEXT NOT NULL, content_md TEXT NOT NULL, created_at INTEGER NOT NULL, updated_at INTEGER NOT NULL, deleted_at INTEGER NULL)');
        $pdo->exec('CREATE TABLE tags (id TEXT PRIMARY KEY, name TEXT NOT NULL, slug TEXT NOT NULL, created_at INTEGER NOT NULL, updated_at INTEGER NOT NULL)');
        $pdo->exec('CREATE UNIQUE INDEX idx_tags_slug ON tags (slug)');
        $pdo->exec('CREATE TABLE note_tags (note_id TEXT NOT NULL, tag_id TEXT NOT NULL, created_at INTEGER NOT NULL, PRIMARY KEY (note_id, tag_id))');

        $pdo->exec("INSERT INTO notes VALUES ('n1', 'u1', 'Work note', 'alpha', 100, 100, NULL)");
        $pdo->exec("INSERT INTO notes VALUES ('n2', 'u1', 'Home note', 'beta', 200, 200, NULL)");
        $pdo->exec("INSERT INTO notes VALUES ('n3', 'u1', 'Deleted note', 'gamma', 300, 300, 123)");
        $pdo->exec("INSERT INTO tags VALUES ('t1', 'Work', 'work', 100, 100)");
        $pdo->exec("INSERT INTO tags VALUES ('t2', 'Home', 'home', 100, 100)");
        $pdo->exec("INSERT INTO note_tags VALUES ('n1', 't1', 100)");
        $pdo->exec("INSERT INTO note_tags VALUES ('n2', 't2', 100)");

        $repo = new NoteRepository($pdo);

        $notes = $repo->listByUser('u1', null, 'work');

        $this->assertCount(1, $notes);
        $this->assertSame('n1', $notes[0]->id);
        $this->assertSame('Work note', $notes[0]->title);
    }

    public function testListByUserCanSearchInTagNameAndSlug(): void
    {
        $pdo = new \PDO('sqlite::memory:');
        $pdo->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION);
        $pdo->setAttribute(\PDO::ATTR_DEFAULT_FETCH_MODE, \PDO::FETCH_ASSOC);
        $pdo->exec('CREATE TABLE notes (id TEXT PRIMARY KEY, user_id TEXT NOT NULL, title TEXT NOT NULL, content_md TEXT NOT NULL, created_at INTEGER NOT NULL, updated_at INTEGER NOT NULL, deleted_at INTEGER NULL)');
        $pdo->exec('CREATE TABLE tags (id TEXT PRIMARY KEY, name TEXT NOT NULL, slug TEXT NOT NULL, created_at INTEGER NOT NULL, updated_at INTEGER NOT NULL)');
        $pdo->exec('CREATE UNIQUE INDEX idx_tags_slug ON tags (slug)');
        $pdo->exec('CREATE TABLE note_tags (note_id TEXT NOT NULL, tag_id TEXT NOT NULL, created_at INTEGER NOT NULL, PRIMARY KEY (note_id, tag_id))');

        $pdo->exec("INSERT INTO notes VALUES ('n1', 'u1', 'Work note', 'alpha', 100, 100, NULL)");
        $pdo->exec("INSERT INTO notes VALUES ('n2', 'u1', 'Home note', 'beta', 200, 200, NULL)");
        $pdo->exec("INSERT INTO tags VALUES ('t1', 'Work Stuff', 'work-stuff', 100, 100)");
        $pdo->exec("INSERT INTO tags VALUES ('t2', 'Home', 'home', 100, 100)");
        $pdo->exec("INSERT INTO note_tags VALUES ('n1', 't1', 100)");
        $pdo->exec("INSERT INTO note_tags VALUES ('n2', 't2', 100)");

        $repo = new NoteRepository($pdo);

        $byName = $repo->listByUser('u1', 'Work Stuff', null);
        $bySlug = $repo->listByUser('u1', 'work-stuff', null);

        $this->assertCount(1, $byName);
        $this->assertSame('n1', $byName[0]->id);
        $this->assertCount(1, $bySlug);
        $this->assertSame('n1', $bySlug[0]->id);
    }

    public function testCreateAndUpdatePersistExpectedFields(): void
    {
        $pdo = new \PDO('sqlite::memory:');
        $pdo->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION);
        $pdo->setAttribute(\PDO::ATTR_DEFAULT_FETCH_MODE, \PDO::FETCH_ASSOC);
        $pdo->exec('CREATE TABLE notes (id TEXT PRIMARY KEY, user_id TEXT NOT NULL, title TEXT NOT NULL, content_md TEXT NOT NULL, created_at INTEGER NOT NULL, updated_at INTEGER NOT NULL, deleted_at INTEGER NULL)');

        $repo = new NoteRepository($pdo);
        $created = new NoteDto('n1', 'u1', 'Title', 'Body', 100, 100, null);
        $repo->create($created);

        $found = $repo->findById('n1', 'u1');
        $this->assertNotNull($found);
        $this->assertSame('Title', $found->title);
        $this->assertSame('Body', $found->contentMd);

        $updated = new NoteDto('n1', 'u1', 'New title', 'New body', 100, 200, null);
        $repo->update($updated);

        $foundUpdated = $repo->findById('n1', 'u1');
        $this->assertNotNull($foundUpdated);
        $this->assertSame('New title', $foundUpdated->title);
        $this->assertSame('New body', $foundUpdated->contentMd);
        $this->assertSame(200, $foundUpdated->updatedAt);
    }
}
