<?php

declare(strict_types=1);

namespace Kampusly\Shared\Domain\ValueObject;

use Kampusly\Shared\Domain\Exception\ValidationException;

final class PaginationQuery
{
    public const DEFAULT_PAGE = 1;
    public const DEFAULT_PER_PAGE = 20;
    public const MAX_PER_PAGE = 100;

    private int $page;
    private int $perPage;

    public function __construct(int $page = self::DEFAULT_PAGE, int $perPage = self::DEFAULT_PER_PAGE)
    {
        $errors = [];
        if ($page < 1) {
            $errors['page'] = ['The page parameter must be at least 1.'];
        }
        if ($perPage < 1 || $perPage > self::MAX_PER_PAGE) {
            $errors['per_page'] = [
                sprintf('The per_page parameter must be between 1 and %d.', self::MAX_PER_PAGE),
            ];
        }

        if ($errors !== []) {
            throw new ValidationException($errors);
        }

        $this->page = $page;
        $this->perPage = $perPage;
    }

    /**
     * @param array<string, mixed> $query
     */
    public static function fromQuery(array $query): self
    {
        $rawPage = $query['page'] ?? self::DEFAULT_PAGE;
        $rawPerPage = $query['per_page'] ?? self::DEFAULT_PER_PAGE;

        $errors = [];
        if (!is_int($rawPage) && !(is_string($rawPage) && preg_match('/^\d+$/', $rawPage) === 1)) {
            $errors['page'] = ['The page parameter must be a positive integer.'];
        }
        if (!is_int($rawPerPage) && !(is_string($rawPerPage) && preg_match('/^\d+$/', $rawPerPage) === 1)) {
            $errors['per_page'] = ['The per_page parameter must be a positive integer.'];
        }

        if ($errors !== []) {
            throw new ValidationException($errors);
        }

        return new self((int) $rawPage, (int) $rawPerPage);
    }

    public function getPage(): int
    {
        return $this->page;
    }

    public function getPerPage(): int
    {
        return $this->perPage;
    }

    public function getOffset(): int
    {
        return ($this->page - 1) * $this->perPage;
    }
}

