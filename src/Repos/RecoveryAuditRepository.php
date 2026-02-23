<?php

declare(strict_types=1);

namespace App\Repos;

use App\Util\Id;

final class RecoveryAuditRepository
{
    public function __construct(private \PDO $pdo) {}

    public function record(string $userId, string $event, string $ip, string $userAgent): void
    {
        $stmt = $this->pdo->prepare(
            'INSERT INTO recovery_audit (id, user_id, event, ip, user_agent, created_at)
             VALUES (:id, :user_id, :event, :ip, :user_agent, :created_at)'
        );
        $stmt->execute([
            'id'         => Id::ulid(),
            'user_id'    => $userId,
            'event'      => $event,
            'ip'         => $ip,
            'user_agent' => $userAgent,
            'created_at' => time(),
        ]);
    }
}
