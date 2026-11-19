<?php

declare(strict_types=1);

namespace Kampusly\Shared\Domain\Exception;

use Throwable;

final class ConfigurationException extends ApplicationException
{
    public function __construct(string $message, ?Throwable $previous = null)
    {
        parent::__construct(
            $message,
            'CONFIGURATION_ERROR',
            500,
            [],
            $previous
        );
    }
}

