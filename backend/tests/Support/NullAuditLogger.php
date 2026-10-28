<?php

declare(strict_types=1);

namespace Kampusly\Tests\Support;

use Kampusly\Shared\Application\Contract\AuditLoggerInterface;

final class NullAuditLogger implements AuditLoggerInterface
{
    /** @var list<array<string, mixed>> */
    private array $logs = [];

    public function record(
        ?int $actorUserId,
        string $action,
        string $entityType,
        ?int $entityId = null,
        array $metadata = [],
        ?string $requestId = null
    ): void {
        $this->logs[] = [
            'actor_user_id' => $actorUserId,
            'action' => $action,
            'entity_type' => $entityType,
            'entity_id' => $entityId,
            'metadata' => $metadata,
            'request_id' => $requestId,
        ];
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function getLogs(): array
    {
        return $this->logs;
    }
}

