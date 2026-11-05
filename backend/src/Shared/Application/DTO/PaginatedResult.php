<?php

declare(strict_types=1);

namespace Kampusly\Shared\Application\DTO;

final class PaginatedResult
{
    /** @var list<array<string, mixed>> */
    private array $items;
    private int $page;
    private int $perPage;
    private int $total;

    /**
     * @param list<array<string, mixed>> $items
     */
    public function __construct(array $items, int $page, int $perPage, int $total)
    {
        $this->items = $items;
        $this->page = max(1, $page);
        $this->perPage = max(1, $perPage);
        $this->total = max(0, $total);
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function getItems(): array
    {
        return $this->items;
    }

    public function getPage(): int
    {
        return $this->page;
    }

    public function getPerPage(): int
    {
        return $this->perPage;
    }

    public function getTotal(): int
    {
        return $this->total;
    }

    public function getTotalPages(): int
    {
        if ($this->total === 0) {
            return 0;
        }

        return (int) ceil($this->total / $this->perPage);
    }

    /**
     * @return array{data: list<array<string, mixed>>, meta: array{page: int, per_page: int, total: int, total_pages: int}}
     */
    public function toArray(): array
    {
        return [
            'data' => $this->items,
            'meta' => [
                'page' => $this->page,
                'per_page' => $this->perPage,
                'total' => $this->total,
                'total_pages' => $this->getTotalPages(),
            ],
        ];
    }
}

