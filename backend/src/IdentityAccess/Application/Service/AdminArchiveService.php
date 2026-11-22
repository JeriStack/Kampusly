<?php

declare(strict_types=1);

namespace Kampusly\IdentityAccess\Application\Service;

use Kampusly\IdentityAccess\Domain\Entity\User;
use Kampusly\IdentityAccess\Domain\Policy\AdminPolicy;
use Kampusly\IdentityAccess\Domain\Repository\AdminArchiveRepositoryInterface;
use Kampusly\Shared\Application\Contract\AuditLoggerInterface;
use Kampusly\Shared\Application\Contract\TransactionManagerInterface;
use Kampusly\Shared\Domain\Exception\BadRequestException;
use Kampusly\Shared\Domain\Exception\ConflictException;
use Kampusly\Shared\Domain\Exception\ForbiddenException;
use Kampusly\Shared\Domain\Exception\ResourceNotFoundException;
use Kampusly\Shared\Domain\ValueObject\PaginationQuery;
use Kampusly\Shared\Infrastructure\Http\RequestValidator;

final class AdminArchiveService
{
    /** @var list<string> */
    public const ENTITY_TYPES = ['users', 'subjects', 'rooms', 'sections', 'schedules'];

    private AdminArchiveRepositoryInterface $archives;
    private AdminPolicy $adminPolicy;
    private TransactionManagerInterface $transactions;
    private AuditLoggerInterface $auditLogger;

    public function __construct(
        AdminArchiveRepositoryInterface $archives,
        AdminPolicy $adminPolicy,
        TransactionManagerInterface $transactions,
        AuditLoggerInterface $auditLogger
    ) {
        $this->archives = $archives;
        $this->adminPolicy = $adminPolicy;
        $this->transactions = $transactions;
        $this->auditLogger = $auditLogger;
    }

    /**
     * @param array<string, mixed> $query
     * @return array<string, mixed>
     */
    public function listArchived(array $query, User $caller): array
    {
        $this->requireAdmin($caller);

        $validator = new RequestValidator($query);
        $validator
            ->optionalIn('type', array_merge(['all'], self::ENTITY_TYPES))
            ->optionalString('q', 120)
            ->validateOrThrow();

        $type = isset($query['type']) && is_string($query['type']) && $query['type'] !== ''
            ? $query['type']
            : 'all';
        $search = isset($query['q']) && is_string($query['q']) ? $query['q'] : null;

        $page = $this->archives->paginateArchived(PaginationQuery::fromQuery($query), $type, $search);
        $payload = $page->toArray();
        $payload['counts'] = $this->archives->countArchivedByType();

        return $payload;
    }

    /**
     * @return array<string, mixed>
     */
    public function archiveRecord(
        string $type,
        int $id,
        User $caller,
        ?string $requestId = null
    ): array {
        $this->requireAdmin($caller);
        $normalizedType = $this->normalizeType($type);

        if ($normalizedType === 'users' && $caller->getId() === $id) {
            throw new ForbiddenException('Administrators cannot archive their own account.');
        }

        $record = $this->archives->findRecordSummary($normalizedType, $id);
        if ($record === null) {
            throw new ResourceNotFoundException('Record not found.');
        }

        if (($record['archived_at'] ?? null) !== null) {
            throw new ConflictException('Record is already archived.', 'RECORD_ALREADY_ARCHIVED');
        }

        $this->transactions->transactional(
            function () use ($normalizedType, $id, $record, $caller, $requestId): void {
                $this->archives->markArchived($normalizedType, $id);
                $this->auditLogger->record(
                    $caller->getId(),
                    'archive.archived',
                    $normalizedType,
                    $id,
                    ['title' => (string) ($record['title'] ?? '')],
                    $requestId
                );
            }
        );

        return $this->archives->findRecordSummary($normalizedType, $id) ?? $record;
    }

    /**
     * @return array<string, mixed>
     */
    public function restoreRecord(
        string $type,
        int $id,
        User $caller,
        ?string $requestId = null
    ): array {
        $this->requireAdmin($caller);
        $normalizedType = $this->normalizeType($type);

        $record = $this->archives->findRecordSummary($normalizedType, $id);
        if ($record === null) {
            throw new ResourceNotFoundException('Record not found.');
        }

        if (($record['archived_at'] ?? null) === null) {
            throw new ConflictException('Record is not currently archived.', 'RECORD_NOT_ARCHIVED');
        }

        $blockReason = $this->archives->getRestoreBlockReason($normalizedType, $id);
        if ($blockReason !== null) {
            throw new ConflictException($blockReason, 'ARCHIVE_RESTORE_BLOCKED');
        }

        $this->transactions->transactional(
            function () use ($normalizedType, $id, $record, $caller, $requestId): void {
                $this->archives->markRestored($normalizedType, $id);
                $this->auditLogger->record(
                    $caller->getId(),
                    'archive.restored',
                    $normalizedType,
                    $id,
                    ['title' => (string) ($record['title'] ?? '')],
                    $requestId
                );
            }
        );

        return $this->archives->findRecordSummary($normalizedType, $id) ?? $record;
    }

    public function permanentlyDeleteRecord(
        string $type,
        int $id,
        User $caller,
        ?string $requestId = null
    ): void {
        $this->requireAdmin($caller);
        $normalizedType = $this->normalizeType($type);

        if ($normalizedType === 'users' && $caller->getId() === $id) {
            throw new ForbiddenException('Administrators cannot delete their own account.');
        }

        $record = $this->archives->findRecordSummary($normalizedType, $id);
        if ($record === null) {
            throw new ResourceNotFoundException('Record not found.');
        }

        if (($record['archived_at'] ?? null) === null) {
            throw new ConflictException(
                'Only archived records can be permanently deleted. Archive the record first.',
                'RECORD_NOT_ARCHIVED'
            );
        }

        $blockReason = $this->archives->getPermanentDeleteBlockReason($normalizedType, $id);
        if ($blockReason !== null) {
            throw new ConflictException(
                'Cannot permanently delete this record: ' . $blockReason,
                'ARCHIVE_DELETE_BLOCKED'
            );
        }

        $this->transactions->transactional(
            function () use ($normalizedType, $id, $record, $caller, $requestId): void {
                $this->archives->permanentlyDelete($normalizedType, $id);
                $this->auditLogger->record(
                    $caller->getId(),
                    'archive.permanently_deleted',
                    $normalizedType,
                    $id,
                    ['title' => (string) ($record['title'] ?? '')],
                    $requestId
                );
            }
        );
    }

    private function requireAdmin(User $caller): void
    {
        if (!$this->adminPolicy->canManageUsers($caller)) {
            throw new ForbiddenException();
        }
    }

    private function normalizeType(string $type): string
    {
        $normalized = strtolower(trim($type));
        if (!in_array($normalized, self::ENTITY_TYPES, true)) {
            throw new BadRequestException(
                sprintf('Unsupported archive entity type "%s".', $type)
            );
        }

        return $normalized;
    }
}
