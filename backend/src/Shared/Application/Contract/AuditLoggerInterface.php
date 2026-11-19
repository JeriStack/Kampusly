<?php

declare(strict_types=1);

namespace Kampusly\Shared\Application\Contract;

interface AuditLoggerInterface
{
    /**
     * Record a security or administrative audit trail entry.
     *
     * @param array<string, mixed> $metadata
     */
    public function record(
        ?int $actorUserId,
        string $action,
        string $entityType,
        ?int $entityId = null,
        array $metadata = [],
        ?string $requestId = null
    ): void;
}

