<?php

declare(strict_types=1);

namespace App\Tests\Repos;

use App\Repos\NoteTagRepository;
use PHPUnit\Framework\TestCase;

final class NoteTagRepositoryTest extends TestCase
{
    public function testListTagNamesForNoteReturnsNames(): void
    {
        $pdo = $this->pdo();
        $pdo->exec('CREATE TABLE tags (id TEXT PRIMARY KEY, name TEXT NOT NULL, slug TEXT NOT NULL, created_at INTEGER NOT NULL, updated_at INTEGER NOT NULL)');
        $pdo->exec('CREATE TABLE note_tags (note_id TEXT NOT NULL, tag_id TEXT NOT NULL, created_at INTEGER NOT NULL, PRIMARY KEY (note_id, tag_id))');
        $pdo->exec("INSERT INTO tags VALUES ('t1', 'Work', 'work', 100, 100)");
        $pdo->exec("INSERT INTO tags VALUES ('t2', 'Home', 'home', 100, 100)");
        $pdo->exec("INSERT INTO note_tags VALUES ('n1', 't1', 100)");
        $pdo->exec("INSERT INTO note_tags VALUES ('n1', 't2', 100)");

        $repo = new NoteTagRepository($pdo);
        $this->assertSame(['Home', 'Work'], $repo->listTagNamesForNote('n1'));
    }

    private function pdo(): \PDO
    {
        $pdo = new \PDO('sqlite::memory:');
        $pdo->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION);
        $pdo->setAttribute(\PDO::ATTR_DEFAULT_FETCH_MODE, \PDO::FETCH_ASSOC);
        return $pdo;
    }
}
