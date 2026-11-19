<?php

declare(strict_types=1);

namespace Kampusly\ClassNotices\Domain\ValueObject;

enum NoticeType: string
{
    case Unavailable = 'unavailable';
    case Cancelled = 'cancelled';
    case Rescheduled = 'rescheduled';
    case RoomChange = 'room_change';

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_map(static fn (self $case): string => $case->value, self::cases());
    }
}

