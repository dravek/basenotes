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

final readonly class RecoveryCodeDto
{
    public function __construct(
        public string   $id,
        public string   $userId,
        public string   $codeHash,
        public int      $createdAt,
        public int|null $usedAt,
    ) {}
}

final class RecoveryCodeRepository
{
    public function __construct(private \PDO $pdo) {}

    /** @param list<string> $hashes */
    public function createMany(string $userId, array $hashes): void
    {
        if ($hashes === []) {
            return;
        }

        $now = \App\Util\Clock::now();
        $stmt = $this->pdo->prepare(
            'INSERT INTO recovery_codes (id, user_id, code_hash, created_at, used_at)
             VALUES (:id, :user_id, :code_hash, :created_at, :used_at)'
        );

        foreach ($hashes as $hash) {
            $stmt->execute([
                'id'         => Id::ulid(),
                'user_id'    => $userId,
                'code_hash'  => $hash,
                'created_at' => $now,
                'used_at'    => null,
            ]);
        }
    }

    public function findValidByHash(string $hash): RecoveryCodeDto|null
    {
        $stmt = $this->pdo->prepare(
            'SELECT id, user_id, code_hash, created_at, used_at
             FROM recovery_codes
             WHERE code_hash = :code_hash AND used_at IS NULL'
        );
        $stmt->execute(['code_hash' => $hash]);
        $row = $stmt->fetch();
        if ($row === false) {
            return null;
        }
        return new RecoveryCodeDto(
            id:        $row['id'],
            userId:    $row['user_id'],
            codeHash:  $row['code_hash'],
            createdAt: (int)$row['created_at'],
            usedAt:    $row['used_at'] !== null ? (int)$row['used_at'] : null,
        );
    }

    public function markUsed(string $id): void
    {
        $stmt = $this->pdo->prepare(
            'UPDATE recovery_codes SET used_at = :used_at WHERE id = :id AND used_at IS NULL'
        );
        $stmt->execute([
            'used_at' => \App\Util\Clock::now(),
            'id'      => $id,
        ]);
    }

    public function invalidateAll(string $userId): void
    {
        $stmt = $this->pdo->prepare(
            'UPDATE recovery_codes SET used_at = :used_at WHERE user_id = :user_id AND used_at IS NULL'
        );
        $stmt->execute([
            'used_at' => \App\Util\Clock::now(),
            'user_id' => $userId,
        ]);
    }
}
