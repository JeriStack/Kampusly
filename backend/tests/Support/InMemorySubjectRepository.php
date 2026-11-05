<?php

declare(strict_types=1);

namespace Kampusly\Tests\Support;

use Kampusly\Academic\Domain\Entity\Subject;
use Kampusly\Academic\Domain\Repository\SubjectRepositoryInterface;
use Kampusly\Shared\Application\DTO\PaginatedResult;
use Kampusly\Shared\Domain\ValueObject\PaginationQuery;
use ReflectionClass;

final class InMemorySubjectRepository implements SubjectRepositoryInterface
{
    /** @var array<int, Subject> */
    private array $subjects = [];
    private int $nextId = 1;
    /** @var array<int, bool> */
    private array $scheduledSubjectIds = [];

    /**
     * @param array<int, Subject> $initial
     */
    public function __construct(array $initial = [])
    {
        foreach ($initial as $subject) {
            $id = $subject->getId() ?? $this->nextId++;
            $this->subjects[$id] = $subject;
            if ($id >= $this->nextId) {
                $this->nextId = $id + 1;
            }
        }
    }

    public function setHasSchedules(int $subjectId, bool $has): void
    {
        $this->scheduledSubjectIds[$subjectId] = $has;
    }

    public function findById(int $id): ?Subject
    {
        return $this->subjects[$id] ?? null;
    }

    public function findByCode(string $subjectCode): ?Subject
    {
        $normalized = strtoupper(trim($subjectCode));
        foreach ($this->subjects as $subject) {
            if (strtoupper($subject->getSubjectCode()) === $normalized) {
                return $subject;
            }
        }
        return null;
    }

    public function paginate(
        PaginationQuery $pagination,
        ?string $status = null,
        ?string $search = null
    ): PaginatedResult {
        $filtered = array_filter($this->subjects, function (Subject $s) use ($status, $search): bool {
            if ($status !== null && $status !== '' && $s->getStatus()->value !== $status) {
                return false;
            }
            if ($search !== null && $search !== '') {
                $q = strtolower($search);
                $matchCode = str_contains(strtolower($s->getSubjectCode()), $q);
                $matchName = str_contains(strtolower($s->getSubjectName()), $q);
                if (!$matchCode && !$matchName) {
                    return false;
                }
            }
            return true;
        });

        $total = count($filtered);
        $offset = $pagination->getOffset();
        $limit = $pagination->getPerPage();
        $slice = array_slice(array_values($filtered), $offset, $limit);
        $items = array_map(static fn (Subject $s): array => $s->toArray(), $slice);

        return new PaginatedResult($items, $pagination->getPage(), $pagination->getPerPage(), $total);
    }

    public function create(Subject $subject): Subject
    {
        $id = $this->nextId++;
        $reflection = new ReflectionClass($subject);
        $prop = $reflection->getProperty('id');
        $prop->setValue($subject, $id);

        $this->subjects[$id] = $subject;
        return $subject;
    }

    public function update(Subject $subject): Subject
    {
        $id = $subject->getId();
        if ($id !== null) {
            $this->subjects[$id] = $subject;
        }
        return $subject;
    }

    public function delete(int $id): bool
    {
        if (isset($this->subjects[$id])) {
            unset($this->subjects[$id]);
            return true;
        }
        return false;
    }

    public function hasSchedules(int $subjectId): bool
    {
        return $this->scheduledSubjectIds[$subjectId] ?? false;
    }
}
