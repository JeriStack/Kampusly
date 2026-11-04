<?php

declare(strict_types=1);

namespace Kampusly\Shared\Domain\Exception;

final class BadRequestException extends ApplicationException
{
    public function __construct(
        string $message = 'The request could not be understood or was malformed.',
        string $errorCode = 'BAD_REQUEST'
    ) {
        parent::__construct($message, $errorCode, 400);
    }
}

