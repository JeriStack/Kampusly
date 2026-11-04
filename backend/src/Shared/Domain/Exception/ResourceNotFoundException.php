<?php

declare(strict_types=1);

namespace Kampusly\Shared\Domain\Exception;

final class ResourceNotFoundException extends ApplicationException
{
    public function __construct(string $message = 'The requested resource was not found.')
    {
        parent::__construct(
            $message,
            'RESOURCE_NOT_FOUND',
            404
        );
    }
}

