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

namespace App\Tests\Repos;

use App\Repos\NoteDto;
use App\Repos\NoteRepository;
use PHPUnit\Framework\TestCase;

final class NoteRepositoryTest extends TestCase
{
    public function testCreateFindUpdateAndSearchByTag(): void
    {
        $pdo = $this->pdo();
        $this->migrate($pdo);

        $repo = new NoteRepository($pdo);
        $note = new NoteDto('n1', 'u1', 'Work note', 'alpha', 100, 100, null);
        $repo->create($note);

        $this->assertSame('Work note', $repo->findById('n1', 'u1')->title);

        $repo->update(new NoteDto('n1', 'u1', 'Updated note', 'beta', 100, 200, null));
        $this->assertSame('Updated note', $repo->findById('n1', 'u1')->title);

        $pdo->exec("INSERT INTO tags VALUES ('t1', 'Work Stuff', 'work-stuff', 100, 100)");
        $pdo->exec("INSERT INTO note_tags VALUES ('n1', 't1', 100)");

        $notes = $repo->listByUser('u1', null, 'work-stuff');
        $this->assertCount(1, $notes);
        $this->assertSame('n1', $notes[0]->id);
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
        $pdo->exec('CREATE TABLE notes (id TEXT PRIMARY KEY, user_id TEXT NOT NULL, title TEXT NOT NULL, content_md TEXT NOT NULL, created_at INTEGER NOT NULL, updated_at INTEGER NOT NULL, deleted_at INTEGER NULL)');
        $pdo->exec('CREATE TABLE tags (id TEXT PRIMARY KEY, name TEXT NOT NULL, slug TEXT NOT NULL, created_at INTEGER NOT NULL, updated_at INTEGER NOT NULL)');
        $pdo->exec('CREATE UNIQUE INDEX idx_tags_slug ON tags (slug)');
        $pdo->exec('CREATE TABLE note_tags (note_id TEXT NOT NULL, tag_id TEXT NOT NULL, created_at INTEGER NOT NULL, PRIMARY KEY (note_id, tag_id))');
    }
}
