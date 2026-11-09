<?php

declare(strict_types=1);

namespace Kampusly\Scheduling\Domain\ValueObject;

use DateTimeImmutable;

final class ScheduleChangeWindow
{
    private ?DateTimeImmutable $startsOn;
    private ?DateTimeImmutable $deadline;
    private DateTimeImmutable $today;

    public function __construct(?string $termStartDate, ?DateTimeImmutable $today = null)
    {
        $this->startsOn = $termStartDate !== null ? new DateTimeImmutable($termStartDate) : null;
        $this->deadline = $this->startsOn !== null ? self::oneMonthAfter($this->startsOn) : null;
        $this->today = ($today ?? new DateTimeImmutable('today'))->setTime(0, 0);
    }

    public function getStartDate(): ?string
    {
        return $this->startsOn?->format('Y-m-d');
    }

    public function getDeadline(): ?string
    {
        return $this->deadline?->format('Y-m-d');
    }

    public function isOpen(): bool
    {
        return $this->startsOn !== null
            && $this->deadline !== null
            && $this->today >= $this->startsOn
            && $this->today <= $this->deadline;
    }

    public function getDaysRemaining(): int
    {
        if (!$this->isOpen() || $this->deadline === null) {
            return 0;
        }

        return (int) $this->today->diff($this->deadline)->format('%a');
    }

    private static function oneMonthAfter(DateTimeImmutable $startDate): DateTimeImmutable
    {
        $nextMonth = $startDate->modify('first day of next month');
        $lastDay = (int) $nextMonth->modify('last day of this month')->format('j');
        $day = min((int) $startDate->format('j'), $lastDay);

        return $nextMonth->setDate(
            (int) $nextMonth->format('Y'),
            (int) $nextMonth->format('n'),
            $day
        );
    }
}
