<?php

declare(strict_types=1);

namespace Kampusly\Announcements\Domain\Service;

/**
 * Announcement messages may carry lightweight formatting tags such as [b]bold[/b],
 * [size=24]big[/size], [color=#dc2626]red[/color], [bg=#fde047]highlight[/bg] and
 * [left]/[center]/[right]. The text is stored as-is and rendered safely by the client;
 * this helper removes the tags wherever plain text is needed (notifications, validation).
 */
final class AnnouncementMarkup
{
    private const TAG_PATTERN = '/\[\/?(?:b|i|u|s|size|color|bg|left|center|right)(?:=[^\[\]]{1,20})?\]/i';

    public static function toPlainText(string $message): string
    {
        return trim((string) preg_replace(self::TAG_PATTERN, '', $message));
    }
}
