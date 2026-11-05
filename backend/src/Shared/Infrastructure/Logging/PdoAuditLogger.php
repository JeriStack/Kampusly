<?php

declare(strict_types=1);

namespace Kampusly\Shared\Infrastructure\Logging;

use Kampusly\Shared\Application\Contract\AuditLoggerInterface;
use PDO;
use Throwable;

final class PdoAuditLogger implements AuditLoggerInterface
{
    private PDO $pdo;
    private LoggerFactory $logger;

    public function __construct(PDO $pdo, LoggerFactory $logger)
    {
        $this->pdo = $pdo;
        $this->logger = $logger;
    }

    public function record(
        ?int $actorUserId,
        string $action,
        string $entityType,
        ?int $entityId = null,
        array $metadata = [],
        ?string $requestId = null
    ): void {
        $sanitizedMeta = $this->logger->redactSecrets($metadata);
        $encodedMeta = $sanitizedMeta !== []
            ? json_encode($sanitizedMeta, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)
            : null;

        try {
            $stmt = $this->pdo->prepare(
                'INSERT INTO `audit_logs` (
                    `actor_user_id`, `action`, `entity_type`, `entity_id`, `metadata_json`, `request_id`
                 ) VALUES (
                    :actor_user_id, :action, :entity_type, :entity_id, :metadata_json, :request_id
                 )'
            );
            $stmt->execute([
                ':actor_user_id' => $actorUserId,
                ':action' => $action,
                ':entity_type' => $entityType,
                ':entity_id' => $entityId,
                ':metadata_json' => is_string($encodedMeta) ? $encodedMeta : null,
                ':request_id' => $requestId,
            ]);
        } catch (Throwable $exception) {
            $this->logger->warning('Database audit log insert failed; falling back to structured log.', [
                'action' => $action,
                'entity_type' => $entityType,
                'entity_id' => $entityId,
                'request_id' => $requestId,
                'error' => $exception->getMessage(),
            ]);
        }
    }
}

