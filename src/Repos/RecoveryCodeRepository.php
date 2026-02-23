<?php

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

        $now = time();
        $stmt = $this->pdo->prepare(
            'INSERT INTO recovery_codes (id, user_id, code_hash, created_at, used_at)
             VALUES (:id, :user_id, :code_hash, :created_at, :used_at)'
        );

        $this->pdo->beginTransaction();
        try {
            foreach ($hashes as $hash) {
                $stmt->execute([
                    'id'         => Id::ulid(),
                    'user_id'    => $userId,
                    'code_hash'  => $hash,
                    'created_at' => $now,
                    'used_at'    => null,
                ]);
            }
            $this->pdo->commit();
        } catch (\Throwable $e) {
            $this->pdo->rollBack();
            throw $e;
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
            'used_at' => time(),
            'id'      => $id,
        ]);
    }

    public function invalidateAll(string $userId): void
    {
        $stmt = $this->pdo->prepare(
            'UPDATE recovery_codes SET used_at = :used_at WHERE user_id = :user_id AND used_at IS NULL'
        );
        $stmt->execute([
            'used_at' => time(),
            'user_id' => $userId,
        ]);
    }
}
