<?php

declare(strict_types=1);

namespace Kampusly\Shared\Domain\Exception;

use RuntimeException;
use Throwable;

class ApplicationException extends RuntimeException
{
    protected string $errorCode;
    protected int $httpStatus;

    /** @var array<string, list<string>> */
    protected array $fieldErrors;

    /** @var array<string, mixed> */
    protected array $metadata;

    /**
     * @param array<string, list<string>> $fieldErrors
     * @param array<string, mixed> $metadata
     */
    public function __construct(
        string $message,
        string $errorCode = 'APPLICATION_ERROR',
        int $httpStatus = 400,
        array $fieldErrors = [],
        ?Throwable $previous = null,
        array $metadata = []
    ) {
        parent::__construct($message, 0, $previous);
        $this->errorCode = $errorCode;
        $this->httpStatus = $httpStatus;
        $this->fieldErrors = $fieldErrors;
        $this->metadata = $metadata;
    }

    public function getErrorCode(): string
    {
        return $this->errorCode;
    }

    public function getHttpStatus(): int
    {
        return $this->httpStatus;
    }

    /**
     * @return array<string, list<string>>
     */
    public function getFieldErrors(): array
    {
        return $this->fieldErrors;
    }

    /**
     * @return array<string, mixed>
     */
    public function getMetadata(): array
    {
        return $this->metadata;
    }
}
