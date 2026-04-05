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
}
