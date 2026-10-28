<?php

declare(strict_types=1);

namespace Kampusly\Notifications\Domain\ValueObject;

enum NotificationType: string
{
    case ClassReminder = 'class_reminder';
    case Announcement = 'announcement';
    case ClassNotice = 'class_notice';
    case ScheduleUpdate = 'schedule_update';

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_map(static fn (self $case): string => $case->value, self::cases());
    }
}

