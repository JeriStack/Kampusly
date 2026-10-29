<?php

declare(strict_types=1);

namespace Kampusly\Tests\Support;

use Kampusly\Academic\Domain\Entity\Section;
use Kampusly\Academic\Domain\Repository\SectionRepositoryInterface;
use Kampusly\Shared\Application\DTO\PaginatedResult;
use Kampusly\Shared\Domain\ValueObject\PaginationQuery;
use ReflectionClass;

final class InMemorySectionRepository implements SectionRepositoryInterface
{
    /** @var array<int, Section> */
    private array $sections = [];
    private int $nextId = 1;
    /** @var array<int, bool> */
    private array $scheduledSectionIds = [];
    /** @var array<int, array<int, array<string, mixed>>> */
    private array $enrolledStudents = [];

    /**
     * @param array<int, Section> $initial
     */
    public function __construct(array $initial = [])
    {
        foreach ($initial as $section) {
            $id = $section->getId() ?? $this->nextId++;
            $this->sections[$id] = $section;
            if ($id >= $this->nextId) {
                $this->nextId = $id + 1;
            }
        }
    }

    public function setHasSchedules(int $sectionId, bool $has): void
    {
        $this->scheduledSectionIds[$sectionId] = $has;
    }

    /**
     * @param array<string, mixed> $student
     */
    public function addEnrolledStudent(int $sectionId, array $student): void
    {
        $this->enrolledStudents[$sectionId][] = $student;
    }

    public function findById(int $id): ?Section
    {
        return $this->sections[$id] ?? null;
    }

    public function findByNameAndTerm(string $sectionName, string $schoolYear, string $semester): ?Section
    {
        $name = trim($sectionName);
        $sy = trim($schoolYear);
        $sem = trim($semester);

        foreach ($this->sections as $sec) {
            if ($sec->getSectionName() === $name && $sec->getSchoolYear() === $sy && $sec->getSemester()->value === $sem) {
                return $sec;
            }
        }
        return null;
    }

    public function paginate(
        PaginationQuery $pagination,
        ?string $schoolYear = null,
        ?string $semester = null,
        ?string $status = null,
        ?string $search = null
    ): PaginatedResult {
        $filtered = array_filter($this->sections, function (Section $sec) use ($schoolYear, $semester, $status, $search): bool {
            if ($schoolYear !== null && $schoolYear !== '' && $sec->getSchoolYear() !== $schoolYear) {
                return false;
            }
            if ($semester !== null && $semester !== '' && $sec->getSemester()->value !== $semester) {
                return false;
            }
            if ($status !== null && $status !== '' && $sec->getStatus()->value !== $status) {
                return false;
            }
            if ($search !== null && $search !== '') {
                $q = strtolower($search);
                $matchName = str_contains(strtolower($sec->getSectionName()), $q);
                $matchProgram = str_contains(strtolower($sec->getProgram()), $q);
                if (!$matchName && !$matchProgram) {
                    return false;
                }
            }
            return true;
        });

        $total = count($filtered);
        $offset = $pagination->getOffset();
        $limit = $pagination->getPerPage();
        $slice = array_slice(array_values($filtered), $offset, $limit);
        $items = array_map(function (Section $sec): array {
            $data = $sec->toArray();
            $data['enrolled_students_count'] = count($this->enrolledStudents[$sec->getId() ?? 0] ?? []);
            return $data;
        }, $slice);

        return new PaginatedResult($items, $pagination->getPage(), $pagination->getPerPage(), $total);
    }

    public function create(Section $section): Section
    {
        $id = $this->nextId++;
        $reflection = new ReflectionClass($section);
        $prop = $reflection->getProperty('id');
        $prop->setValue($section, $id);

        $this->sections[$id] = $section;
        return $section;
    }

    public function update(Section $section): Section
    {
        $id = $section->getId();
        if ($id !== null) {
            $this->sections[$id] = $section;
        }
        return $section;
    }

    public function delete(int $id): bool
    {
        if (isset($this->sections[$id])) {
            unset($this->sections[$id]);
            return true;
        }
        return false;
    }

    public function hasSchedules(int $sectionId): bool
    {
        return $this->scheduledSectionIds[$sectionId] ?? false;
    }

    public function hasStudents(int $sectionId): bool
    {
        return !empty($this->enrolledStudents[$sectionId]);
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function findStudents(int $sectionId): array
    {
        return $this->enrolledStudents[$sectionId] ?? [];
    }
}
