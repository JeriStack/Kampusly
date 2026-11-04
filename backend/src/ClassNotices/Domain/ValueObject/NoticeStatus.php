<?php

declare(strict_types=1);

namespace Kampusly\ClassNotices\Domain\ValueObject;

enum NoticeStatus: string
{
    case PendingApproval = 'pending_approval';
    case Published = 'published';
    case Rejected = 'rejected';
    case Withdrawn = 'withdrawn';

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_map(static fn (self $case): string => $case->value, self::cases());
    }
}

