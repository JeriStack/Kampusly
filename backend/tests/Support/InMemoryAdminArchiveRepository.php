<?php

declare(strict_types=1);

namespace Kampusly\Tests\Support;

use Kampusly\IdentityAccess\Domain\Repository\AdminArchiveRepositoryInterface;
use Kampusly\Shared\Application\DTO\PaginatedResult;
use Kampusly\Shared\Domain\ValueObject\PaginationQuery;

final class InMemoryAdminArchiveRepository implements AdminArchiveRepositoryInterface
{
    /** @var array<string, array<int, array<string, mixed>>> */
    private array $records = [
        'users' => [],
        'subjects' => [],
        'rooms' => [],
        'sections' => [],
        'schedules' => [],
    ];

    /** @var array<string, string> */
    private array $restoreBlocks = [];

    /** @var array<string, string> */
    private array $deleteBlocks = [];

    /**
     * @param array<string, mixed> $record
     */
    public function seed(string $type, int $id, array $record): void
    {
        $this->records[$type][$id] = array_merge([
            'entity_type' => $type,
            'id' => $id,
            'title' => sprintf('%s #%d', ucfirst($type), $id),
            'subtitle' => 'Test record',
            'badge' => strtoupper($type),
            'status' => 'active',
            'archived_at' => null,
        ], $record);
    }

    public function setRestoreBlockReason(string $type, int $id, ?string $reason): void
    {
        $key = $type . ':' . $id;
        if ($reason === null) {
            unset($this->restoreBlocks[$key]);
            return;
        }
        $this->restoreBlocks[$key] = $reason;
    }

    public function setDeleteBlockReason(string $type, int $id, ?string $reason): void
    {
        $key = $type . ':' . $id;
        if ($reason === null) {
            unset($this->deleteBlocks[$key]);
            return;
        }
        $this->deleteBlocks[$key] = $reason;
    }

    public function countArchivedByType(): array
    {
        $counts = [];
        foreach (['users', 'subjects', 'rooms', 'sections', 'schedules'] as $type) {
            $counts[$type] = count(array_filter(
                $this->records[$type],
                static fn (array $item): bool => ($item['archived_at'] ?? null) !== null
            ));
        }

        return [
            'all' => array_sum($counts),
            'users' => $counts['users'],
            'subjects' => $counts['subjects'],
            'rooms' => $counts['rooms'],
            'sections' => $counts['sections'],
            'schedules' => $counts['schedules'],
        ];
    }

    public function paginateArchived(
        PaginationQuery $pagination,
        ?string $type = null,
        ?string $search = null
    ): PaginatedResult {
        $types = ($type !== null && $type !== '' && $type !== 'all')
            ? [$type]
            : ['users', 'subjects', 'rooms', 'sections', 'schedules'];

        $items = [];
        foreach ($types as $t) {
            foreach ($this->records[$t] ?? [] as $item) {
                if (($item['archived_at'] ?? null) === null) {
                    continue;
                }
                if ($search !== null && trim($search) !== '') {
                    $needle = strtolower(trim($search));
                    $haystack = strtolower(
                        (string) ($item['title'] ?? '') . ' ' . (string) ($item['subtitle'] ?? '')
                    );
                    if (!str_contains($haystack, $needle)) {
                        continue;
                    }
                }
                $block = $this->getPermanentDeleteBlockReason($t, (int) $item['id']);
                $item['can_permanently_delete'] = $block === null;
                $item['delete_block_reason'] = $block;
                $items[] = $item;
            }
        }

        $total = count($items);
        $slice = array_slice($items, $pagination->getOffset(), $pagination->getPerPage());

        return new PaginatedResult($slice, $pagination->getPage(), $pagination->getPerPage(), $total);
    }

    public function findRecordSummary(string $type, int $id): ?array
    {
        return $this->records[$type][$id] ?? null;
    }

    public function markArchived(string $type, int $id): void
    {
        if (!isset($this->records[$type][$id])) {
            return;
        }
        $this->records[$type][$id]['archived_at'] = '2026-11-22 09:00:00';
        $this->records[$type][$id]['status'] = $type === 'schedules' ? 'cancelled' : 'inactive';
    }

    public function markRestored(string $type, int $id): void
    {
        if (!isset($this->records[$type][$id])) {
            return;
        }
        $this->records[$type][$id]['archived_at'] = null;
        $this->records[$type][$id]['status'] = $type === 'schedules' ? 'published' : 'active';
    }

    public function getRestoreBlockReason(string $type, int $id): ?string
    {
        return $this->restoreBlocks[$type . ':' . $id] ?? null;
    }

    public function getPermanentDeleteBlockReason(string $type, int $id): ?string
    {
        return $this->deleteBlocks[$type . ':' . $id] ?? null;
    }

    public function permanentlyDelete(string $type, int $id): void
    {
        unset($this->records[$type][$id]);
    }
}
