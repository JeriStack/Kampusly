<?php

declare(strict_types=1);

namespace Kampusly\Scheduling\Domain\ValueObject;

use Kampusly\Shared\Domain\Exception\ValidationException;
use Kampusly\Shared\Infrastructure\Http\RequestValidator;

final class TimeSlot
{
    private string $startTime;
    private string $endTime;
    private int $startSeconds;
    private int $endSeconds;

    public function __construct(string $startTime, string $endTime)
    {
        $startSec = RequestValidator::parseTimeToSeconds(trim($startTime));
        $endSec = RequestValidator::parseTimeToSeconds(trim($endTime));

        $errors = [];
        if ($startSec === null) {
            $errors['start_time'] = ['The start_time field must be a valid 24-hour time (HH:MM or HH:MM:SS).'];
        }
        if ($endSec === null) {
            $errors['end_time'] = ['The end_time field must be a valid 24-hour time (HH:MM or HH:MM:SS).'];
        }
        if ($startSec !== null && $endSec !== null && $startSec >= $endSec) {
            $errors['end_time'] = ['The end_time field must be strictly later than start_time.'];
        }

        if ($errors !== []) {
            throw new ValidationException($errors);
        }

        /** @var int $validStart */
        $validStart = $startSec;
        /** @var int $validEnd */
        $validEnd = $endSec;

        $this->startSeconds = $validStart;
        $this->endSeconds = $validEnd;
        $this->startTime = self::formatSeconds($validStart);
        $this->endTime = self::formatSeconds($validEnd);
    }

    /**
     * Half-open interval overlap check: max(S1, S2) < min(E1, E2).
     * Adjacent classes where E1 === S2 do NOT conflict.
     */
    public function overlapsWith(self $other): bool
    {
        return max($this->startSeconds, $other->startSeconds) < min($this->endSeconds, $other->endSeconds);
    }

    public function getStartTime(): string
    {
        return $this->startTime;
    }

    public function getEndTime(): string
    {
        return $this->endTime;
    }

    public function getStartSeconds(): int
    {
        return $this->startSeconds;
    }

    public function getEndSeconds(): int
    {
        return $this->endSeconds;
    }

    public function getDurationMinutes(): int
    {
        return (int) (($this->endSeconds - $this->startSeconds) / 60);
    }

    private static function formatSeconds(int $totalSeconds): string
    {
        $hours = intdiv($totalSeconds, 3600);
        $minutes = intdiv($totalSeconds % 3600, 60);
        $seconds = $totalSeconds % 60;

        return sprintf('%02d:%02d:%02d', $hours, $minutes, $seconds);
    }
}

