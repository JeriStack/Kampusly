<?php

declare(strict_types=1);

namespace Kampusly\ClassNotices\Application\Service;

use Kampusly\ClassNotices\Domain\Entity\ClassNotice;
use Kampusly\ClassNotices\Domain\Repository\ClassNoticeRepositoryInterface;
use Kampusly\ClassNotices\Domain\ValueObject\NoticeStatus;
use Kampusly\ClassNotices\Domain\ValueObject\NoticeType;
use Kampusly\Shared\Application\Contract\AuditLoggerInterface;
use Kampusly\Shared\Application\Contract\TransactionManagerInterface;
use Kampusly\Shared\Application\DTO\PaginatedResult;
use Kampusly\Shared\Domain\ValueObject\PaginationQuery;
use Kampusly\Shared\Infrastructure\Http\RequestValidator;

final class ClassNoticeFoundationService
{
    private ClassNoticeRepositoryInterface $notices;
    private TransactionManagerInterface $transactions;
    private AuditLoggerInterface $auditLogger;

    public function __construct(
        ClassNoticeRepositoryInterface $notices,
        TransactionManagerInterface $transactions,
        AuditLoggerInterface $auditLogger
    ) {
        $this->notices = $notices;
        $this->transactions = $transactions;
        $this->auditLogger = $auditLogger;
    }

    /**
     * @param array<string, mixed> $query
     */
    public function listNotices(array $query): PaginatedResult
    {
        $validator = new RequestValidator($query);
        $validator
            ->optionalPositiveInt('schedule_id')
            ->optionalPositiveInt('instructor_id')
            ->optionalIn('status', NoticeStatus::values())
            ->optionalDate('affected_date')
            ->validateOrThrow();

        $pagination = PaginationQuery::fromQuery($query);
        $filters = [
            'schedule_id' => isset($query['schedule_id']) && $query['schedule_id'] !== ''
                ? (int) $query['schedule_id']
                : null,
            'instructor_id' => isset($query['instructor_id']) && $query['instructor_id'] !== ''
                ? (int) $query['instructor_id']
                : null,
            'status' => isset($query['status']) ? (string) $query['status'] : null,
            'affected_date' => isset($query['affected_date']) ? (string) $query['affected_date'] : null,
        ];

        return $this->notices->paginate($pagination, $filters);
    }

    /**
     * @param array<string, mixed> $payload
     */
    public function submitNotice(
        array $payload,
        int $instructorId,
        ?int $actorUserId = null,
        ?string $requestId = null
    ): ClassNotice {
        $validator = new RequestValidator($payload);
        $validator
            ->requirePositiveInt('schedule_id')
            ->requireIn('notice_type', NoticeType::values())
            ->requireDate('affected_date')
            ->requireString('title', 3, 180)
            ->requireString('message', 5, 2000)
            ->optionalPositiveInt('new_room_id')
            ->optionalDate('new_date')
            ->optionalTime('new_start_time')
            ->optionalTime('new_end_time')
            ->validateOrThrow();

        $noticeType = NoticeType::from((string) $payload['notice_type']);
        if ($noticeType === NoticeType::RoomChange) {
            $validator->requirePositiveInt('new_room_id')->validateOrThrow();
        } elseif ($noticeType === NoticeType::Rescheduled) {
            $validator
                ->requireDate('new_date')
                ->requireTime('new_start_time')
                ->requireTime('new_end_time')
                ->requireTimeOrder('new_start_time', 'new_end_time')
                ->validateOrThrow();
        }

        $notice = new ClassNotice(
            null,
            (int) $payload['schedule_id'],
            $instructorId,
            $noticeType,
            (string) $payload['affected_date'],
            (string) $payload['title'],
            (string) $payload['message'],
            isset($payload['new_room_id']) && $payload['new_room_id'] !== '' ? (int) $payload['new_room_id'] : null,
            isset($payload['new_date']) && $payload['new_date'] !== '' ? (string) $payload['new_date'] : null,
            isset($payload['new_start_time']) && $payload['new_start_time'] !== ''
                ? (string) $payload['new_start_time']
                : null,
            isset($payload['new_end_time']) && $payload['new_end_time'] !== ''
                ? (string) $payload['new_end_time']
                : null,
            NoticeStatus::PendingApproval
        );

        /** @var ClassNotice $created */
        $created = $this->transactions->transactional(function () use ($notice, $actorUserId, $requestId): ClassNotice {
            $persisted = $this->notices->create($notice);
            $this->auditLogger->record(
                $actorUserId,
                'class_notice.submitted',
                'class_notice',
                $persisted->getId(),
                [
                    'notice_type' => $persisted->getNoticeType()->value,
                    'affected_date' => $persisted->getAffectedDate(),
                ],
                $requestId
            );

            return $persisted;
        });

        return $created;
    }
}

