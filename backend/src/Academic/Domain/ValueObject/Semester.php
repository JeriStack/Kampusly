<?php

declare(strict_types=1);

namespace Kampusly\Academic\Domain\ValueObject;

enum Semester: string
{
    case First = '1st';
    case Second = '2nd';
    case Summer = 'Summer';

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_map(static fn (self $case): string => $case->value, self::cases());
    }
}

