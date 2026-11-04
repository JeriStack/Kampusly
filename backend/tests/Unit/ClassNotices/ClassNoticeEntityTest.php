<?php

declare(strict_types=1);

namespace Kampusly\Tests\Unit\ClassNotices;

use Kampusly\ClassNotices\Domain\Entity\ClassNotice;
use Kampusly\ClassNotices\Domain\ValueObject\NoticeStatus;
use Kampusly\ClassNotices\Domain\ValueObject\NoticeType;
use Kampusly\Tests\Support\TestCase;

final class ClassNoticeEntityTest extends TestCase
{
    public function testClassNoticeSerializationAndEnums(): void
    {
        $notice = new ClassNotice(
            5,
            12,
            3,
            NoticeType::RoomChange,
            '2026-11-12',
            'Relocation to RM-304',
            'Relocating due to audio system repair.',
            15, // new room id
            null,
            null,
            null,
            NoticeStatus::Published
        );

        $this->assertSame(NoticeType::RoomChange, $notice->getNoticeType());
        $this->assertSame(NoticeStatus::Published, $notice->getStatus());
        $this->assertSame(15, $notice->getNewRoomId());

        $array = $notice->toArray();
        $this->assertSame('room_change', $array['notice_type']);
        $this->assertSame('published', $array['status']);
        $this->assertSame('2026-11-12', $array['affected_date']);
        $this->assertSame(15, $array['new_room_id']);
    }
}

