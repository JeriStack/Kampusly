<?php

declare(strict_types=1);

namespace Kampusly\Shared\Domain\Exception;

final class ForbiddenException extends ApplicationException
{
    public function __construct(string $message = 'You do not have permission to perform this action.')
    {
        parent::__construct($message, 'FORBIDDEN', 403);
    }
}

