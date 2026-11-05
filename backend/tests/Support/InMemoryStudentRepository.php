<?php

declare(strict_types=1);

namespace Kampusly\Tests\Support;

use Kampusly\Academic\Domain\Entity\StudentProfile;
use Kampusly\Academic\Domain\Repository\StudentRepositoryInterface;
use Kampusly\Shared\Application\DTO\PaginatedResult;
use Kampusly\Shared\Domain\ValueObject\PaginationQuery;
use ReflectionClass;

final class InMemoryStudentRepository implements StudentRepositoryInterface
{
    /** @var array<int, StudentProfile> */
    private array $students = [];
    private int $nextId = 1;
    /** @var array<int, bool> */
    private array $scheduledStudentIds = [];

    /**
     * @param array<int, StudentProfile> $initial
     */
    public function __construct(array $initial = [])
    {
        foreach ($initial as $student) {
            $id = $student->getId() ?? $this->nextId++;
            $this->students[$id] = $student;
            if ($id >= $this->nextId) {
                $this->nextId = $id + 1;
            }
        }
    }

    public function setHasSchedules(int $studentId, bool $has): void
    {
        $this->scheduledStudentIds[$studentId] = $has;
    }

    public function findById(int $id): ?StudentProfile
    {
        return $this->students[$id] ?? null;
    }

    public function findByUserId(int $userId): ?StudentProfile
    {
        foreach ($this->students as $student) {
            if ($student->getUserId() === $userId) {
                return $student;
            }
        }
        return null;
    }

    public function findByStudentNumber(string $studentNumber): ?StudentProfile
    {
        $normalized = strtoupper(trim($studentNumber));
        foreach ($this->students as $student) {
            if (strtoupper($student->getStudentNumber()) === $normalized) {
                return $student;
            }
        }
        return null;
    }

    public function paginate(
        PaginationQuery $pagination,
        ?int $sectionId = null,
        ?string $status = null,
        ?string $search = null
    ): PaginatedResult {
        $filtered = array_filter($this->students, function (StudentProfile $s) use ($sectionId, $status, $search): bool {
            if ($sectionId !== null && $sectionId > 0 && $s->getSectionId() !== $sectionId) {
                return false;
            }
            if ($status !== null && $status !== '' && $s->getStatus() !== $status) {
                return false;
            }
            if ($search !== null && $search !== '') {
                $q = strtolower($search);
                $matchNum = str_contains(strtolower($s->getStudentNumber()), $q);
                $matchName = str_contains(strtolower($s->getFullName()), $q);
                $matchEmail = $s->getEmail() !== null && str_contains(strtolower($s->getEmail()), $q);
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
        $items = array_map(static fn (StudentProfile $s): array => $s->toArray(), $slice);

        return new PaginatedResult($items, $pagination->getPage(), $pagination->getPerPage(), $total);
    }

    public function create(StudentProfile $student): StudentProfile
    {
        $id = $this->nextId++;
        $reflection = new ReflectionClass($student);
        $prop = $reflection->getProperty('id');
        $prop->setValue($student, $id);

        $this->students[$id] = $student;
        return $student;
    }

    public function update(StudentProfile $student): StudentProfile
    {
        $id = $student->getId();
        if ($id !== null) {
            $this->students[$id] = $student;
        }
        return $student;
    }

    public function hasSchedules(int $studentId): bool
    {
        return $this->scheduledStudentIds[$studentId] ?? false;
    }
}
