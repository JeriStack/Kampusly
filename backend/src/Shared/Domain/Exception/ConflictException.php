<?php

declare(strict_types=1);

namespace Kampusly\Shared\Domain\Exception;

final class ConflictException extends ApplicationException
{
    /** @var list<array<string, mixed>> */
    private array $conflicts;

    /**
     * @param list<array<string, mixed>> $conflicts
     */
    public function __construct(
        string $message = 'The request conflicts with the current state of the target resource.',
        string $errorCode = 'CONFLICT',
        array $conflicts = []
    ) {
        $metadata = $conflicts !== [] ? ['conflicts' => $conflicts] : [];
        parent::__construct($message, $errorCode, 409, [], null, $metadata);
        $this->conflicts = $conflicts;
    }

    /**
     * @param list<array<string, mixed>> $conflicts
     */
    public static function scheduleConflict(
        array $conflicts,
        string $message = 'One or more scheduling conflicts were detected for the requested time slot.'
    ): self {
        return new self($message, 'SCHEDULE_CONFLICT', $conflicts);
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function getConflicts(): array
    {
        return $this->conflicts;
    }
}

