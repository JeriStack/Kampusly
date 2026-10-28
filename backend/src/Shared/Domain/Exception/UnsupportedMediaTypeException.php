<?php

declare(strict_types=1);

namespace Kampusly\Shared\Domain\Exception;

final class UnsupportedMediaTypeException extends ApplicationException
{
    public function __construct(
        string $message = 'Unsupported Content-Type header. Expected application/json.'
    ) {
        parent::__construct($message, 'UNSUPPORTED_MEDIA_TYPE', 415);
    }
}

