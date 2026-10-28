<?php

declare(strict_types=1);

namespace Kampusly\Scheduling\Domain\ValueObject;

enum ScheduleStatus: string
{
    case Draft = 'draft';
    case Published = 'published';
    case Cancelled = 'cancelled';

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_map(static fn (self $case): string => $case->value, self::cases());
    }
}

