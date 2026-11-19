<?php

declare(strict_types=1);

namespace Kampusly\Tests\Support;

use Kampusly\Academic\Domain\Entity\InstructorProfile;
use Kampusly\Academic\Domain\Repository\InstructorRepositoryInterface;
use Kampusly\Shared\Application\DTO\PaginatedResult;
use Kampusly\Shared\Domain\ValueObject\PaginationQuery;
use ReflectionClass;

final class InMemoryInstructorRepository implements InstructorRepositoryInterface
{
    /** @var array<int, InstructorProfile> */
    private array $instructors = [];
    private int $nextId = 1;
    /** @var array<int, bool> */
    private array $scheduledInstructorIds = [];

    /**
     * @param array<int, InstructorProfile> $initial
     */
    public function __construct(array $initial = [])
    {
        foreach ($initial as $instructor) {
            $id = $instructor->getId() ?? $this->nextId++;
            $this->instructors[$id] = $instructor;
            if ($id >= $this->nextId) {
                $this->nextId = $id + 1;
            }
        }
    }

    public function setHasSchedules(int $instructorId, bool $has): void
    {
        $this->scheduledInstructorIds[$instructorId] = $has;
    }

    public function findById(int $id): ?InstructorProfile
    {
        return $this->instructors[$id] ?? null;
    }

    public function findByUserId(int $userId): ?InstructorProfile
    {
        foreach ($this->instructors as $instructor) {
            if ($instructor->getUserId() === $userId) {
                return $instructor;
            }
        }
        return null;
    }

    public function findByEmployeeNumber(string $employeeNumber): ?InstructorProfile
    {
        $normalized = strtoupper(trim($employeeNumber));
        foreach ($this->instructors as $instructor) {
            if (strtoupper($instructor->getEmployeeNumber()) === $normalized) {
                return $instructor;
            }
        }
        return null;
    }

    public function paginate(
        PaginationQuery $pagination,
        ?string $department = null,
        ?string $status = null,
        ?string $search = null
    ): PaginatedResult {
        $filtered = array_filter($this->instructors, function (InstructorProfile $i) use ($department, $status, $search): bool {
            if ($department !== null && $department !== '' && $i->getDepartment() !== $department) {
                return false;
            }
            if ($status !== null && $status !== '' && $i->getStatus() !== $status) {
                return false;
            }
            if ($search !== null && $search !== '') {
                $q = strtolower($search);
                $matchNum = str_contains(strtolower($i->getEmployeeNumber()), $q);
                $matchName = str_contains(strtolower($i->getFullName()), $q);
                $matchEmail = $i->getEmail() !== null && str_contains(strtolower($i->getEmail()), $q);
                if (!$matchNum && !$matchName && !$matchEmail) {
                    return false;
                }
            }
            return true;
        });

        $total = count($filtered);
        $offset = $pagination->getOffset();
        $limit = $pagination->getPerPage();
        $slice = array_slice(array_values($filtered), $offset, $limit);
        $items = array_map(static fn (InstructorProfile $i): array => $i->toArray(), $slice);

        return new PaginatedResult($items, $pagination->getPage(), $pagination->getPerPage(), $total);
    }

    public function create(InstructorProfile $instructor): InstructorProfile
    {
        $id = $this->nextId++;
        $reflection = new ReflectionClass($instructor);
        $prop = $reflection->getProperty('id');
        $prop->setValue($instructor, $id);

        $this->instructors[$id] = $instructor;
        return $instructor;
    }

    public function update(InstructorProfile $instructor): InstructorProfile
    {
        $id = $instructor->getId();
        if ($id !== null) {
            $this->instructors[$id] = $instructor;
        }
        return $instructor;
    }

    public function hasSchedules(int $instructorId): bool
    {
        return $this->scheduledInstructorIds[$instructorId] ?? false;
    }
}
