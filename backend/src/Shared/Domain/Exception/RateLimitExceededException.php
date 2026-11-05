<?php

declare(strict_types=1);

namespace Kampusly\Shared\Domain\Exception;

final class RateLimitExceededException extends ApplicationException
{
    private int $retryAfterSeconds;

    public function __construct(
        int $retryAfterSeconds = 60,
        string $message = 'Too many requests. Please wait before trying again.'
    ) {
        parent::__construct(
            $message,
            'RATE_LIMITED',
            429,
            [],
            null,
            ['retry_after_seconds' => $retryAfterSeconds]
        );
        $this->retryAfterSeconds = $retryAfterSeconds;
    }

    public function getRetryAfterSeconds(): int
    {
        return $this->retryAfterSeconds;
    }
}

