<?php

declare(strict_types=1);

namespace Kampusly\Shared\Domain\Exception;

final class MethodNotAllowedException extends ApplicationException
{
    /** @var list<string> */
    private array $allowedMethods;

    /**
     * @param list<string> $allowedMethods
     */
    public function __construct(array $allowedMethods, string $message = 'HTTP method not allowed for this route.')
    {
        parent::__construct(
            $message,
            'METHOD_NOT_ALLOWED',
            405
        );
        $this->allowedMethods = $allowedMethods;
    }

    /**
     * @return list<string>
     */
    public function getAllowedMethods(): array
    {
        return $this->allowedMethods;
    }
}

