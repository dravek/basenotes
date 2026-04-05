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
