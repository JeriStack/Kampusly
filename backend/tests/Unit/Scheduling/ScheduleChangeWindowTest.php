<?php

declare(strict_types=1);

namespace Kampusly\Tests\Unit\Scheduling;

use DateTimeImmutable;
use Kampusly\Scheduling\Domain\ValueObject\ScheduleChangeWindow;
use Kampusly\Tests\Support\TestCase;

final class ScheduleChangeWindowTest extends TestCase
{
    public function testWindowIsClosedWithoutTermStartDate(): void
    {
        $window = new ScheduleChangeWindow(null, new DateTimeImmutable('2026-11-09'));

        $this->assertNull($window->getStartDate());
        $this->assertNull($window->getDeadline());
        $this->assertFalse($window->isOpen());
    }

    public function testRequestWindowIncludesStartAndOneMonthDeadline(): void
    {
        $startDay = new ScheduleChangeWindow('2026-10-10', new DateTimeImmutable('2026-10-10'));
        $deadline = new ScheduleChangeWindow('2026-10-10', new DateTimeImmutable('2026-11-10'));
        $afterDeadline = new ScheduleChangeWindow('2026-10-10', new DateTimeImmutable('2026-11-11'));

        $this->assertTrue($startDay->isOpen());
        $this->assertSame('2026-11-10', $deadline->getDeadline());
        $this->assertTrue($deadline->isOpen());
        $this->assertSame(0, $deadline->getDaysRemaining());
        $this->assertFalse($afterDeadline->isOpen());
    }

    public function testMonthEndDeadlineClampsToLastDayOfFollowingMonth(): void
    {
        $commonYear = new ScheduleChangeWindow('2026-01-31', new DateTimeImmutable('2026-01-31'));
        $leapYear = new ScheduleChangeWindow('2024-01-31', new DateTimeImmutable('2024-01-31'));

        $this->assertSame('2026-02-28', $commonYear->getDeadline());
        $this->assertSame('2024-02-29', $leapYear->getDeadline());
    }

    public function testRequestWindowIsClosedBeforeSemesterBegins(): void
    {
        $window = new ScheduleChangeWindow('2026-11-10', new DateTimeImmutable('2026-11-09'));

        $this->assertFalse($window->isOpen());
    }
}
