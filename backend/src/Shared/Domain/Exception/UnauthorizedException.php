<?php

declare(strict_types=1);

namespace Kampusly\Shared\Domain\Exception;

final class UnauthorizedException extends ApplicationException
{
    public function __construct(string $message = 'Authentication is required to access this resource.')
    {
        parent::__construct($message, 'UNAUTHENTICATED', 401);
    }
}

