<?php

declare(strict_types=1);

namespace App\Repos;

use App\Util\Id;

final class AuditLogRepository
{
    public function __construct(private \PDO $pdo) {}

    public function record(
        ?string $actorUserId,
        ?string $targetUserId,
        string $event,
        string $ip,
        string $userAgent,
        ?string $entityType = null,
        ?string $entityId = null,
        array $metadata = [],
    ): void {
        $stmt = $this->pdo->prepare(
            'INSERT INTO audit_log (
                 id, actor_user_id, target_user_id, event, entity_type, entity_id,
                 ip, user_agent, metadata_json, created_at
             ) VALUES (
                 :id, :actor_user_id, :target_user_id, :event, :entity_type, :entity_id,
                 :ip, :user_agent, :metadata_json, :created_at
             )'
        );
        $stmt->execute([
            'id'             => Id::ulid(),
            'actor_user_id'  => $actorUserId,
            'target_user_id' => $targetUserId,
            'event'          => $event,
            'entity_type'    => $entityType,
            'entity_id'      => $entityId,
            'ip'             => $ip,
            'user_agent'     => mb_substr($userAgent, 0, 500),
            'metadata_json'  => json_encode($metadata, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) ?: '{}',
            'created_at'     => \App\Util\Clock::now(),
        ]);
    }

    /** @return list<array<string, mixed>> */
    public function listRecent(int $limit = 100): array
    {
        $stmt = $this->pdo->prepare(
            'SELECT a.id, a.event, a.ip, a.user_agent, a.entity_type, a.entity_id, a.metadata_json, a.created_at,
                    au.email AS actor_email,
                    tu.email AS target_email
             FROM audit_log a
             LEFT JOIN users au ON au.id = a.actor_user_id
             LEFT JOIN users tu ON tu.id = a.target_user_id
             ORDER BY a.created_at DESC, a.id DESC
             LIMIT :limit'
        );
        $stmt->bindValue(':limit', $limit, \PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll();
    }
}
