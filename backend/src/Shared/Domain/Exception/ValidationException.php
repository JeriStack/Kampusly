<?php

declare(strict_types=1);

namespace Kampusly\Shared\Domain\Exception;

final class ValidationException extends ApplicationException
{
    /**
     * @param array<string, list<string>> $fieldErrors
     */
    public function __construct(
        array $fieldErrors,
        string $message = 'The submitted data failed validation.'
    ) {
        parent::__construct(
            $message,
            'VALIDATION_ERROR',
            422,
            $fieldErrors
        );
    }
}

