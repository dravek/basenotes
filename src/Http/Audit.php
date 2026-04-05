<?php

declare(strict_types=1);

namespace App\Http;

use App\Auth\Session;
use App\Repos\AuditLogRepository;

final class Audit
{
    public static function record(
        AuditLogRepository $auditRepo,
        string $event,
        ?string $targetUserId = null,
        ?string $entityType = null,
        ?string $entityId = null,
        array $metadata = [],
    ): void {
        $auditRepo->record(
            Session::userId(),
            $targetUserId,
            $event,
            self::ip(),
            self::userAgent(),
            $entityType,
            $entityId,
            $metadata,
        );
    }

    public static function recordAnonymous(
        AuditLogRepository $auditRepo,
        string $event,
        ?string $targetUserId = null,
        ?string $entityType = null,
        ?string $entityId = null,
        array $metadata = [],
    ): void {
        $auditRepo->record(
            null,
            $targetUserId,
            $event,
            self::ip(),
            self::userAgent(),
            $entityType,
            $entityId,
            $metadata,
        );
    }

    private static function ip(): string
    {
        return $_SERVER['REMOTE_ADDR'] ?? 'unknown';
    }

    private static function userAgent(): string
    {
        return $_SERVER['HTTP_USER_AGENT'] ?? 'unknown';
    }
}
