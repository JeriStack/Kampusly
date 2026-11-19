<?php

declare(strict_types=1);

namespace Kampusly\Academic\Infrastructure\Persistence;

use Kampusly\Academic\Domain\Entity\Section;
use Kampusly\Academic\Domain\Repository\SectionRepositoryInterface;
use Kampusly\Shared\Application\DTO\PaginatedResult;
use Kampusly\Shared\Domain\ValueObject\PaginationQuery;
use PDO;

final class PdoSectionRepository implements SectionRepositoryInterface
{
    private PDO $pdo;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    public function findById(int $id): ?Section
    {
        $stmt = $this->pdo->prepare('SELECT * FROM `sections` WHERE `id` = :id LIMIT 1');
        $stmt->execute([':id' => $id]);
        $row = $stmt->fetch();

        return is_array($row) ? Section::fromDatabaseRow($row) : null;
    }

    public function findByNameAndTerm(string $sectionName, string $schoolYear, string $semester): ?Section
    {
        $stmt = $this->pdo->prepare(
            'SELECT * FROM `sections`
             WHERE `section_name` = :name AND `school_year` = :sy AND `semester` = :sem
             LIMIT 1'
        );
        $stmt->execute([
            ':name' => trim($sectionName),
            ':sy' => trim($schoolYear),
            ':sem' => trim($semester),
        ]);
        $row = $stmt->fetch();

        return is_array($row) ? Section::fromDatabaseRow($row) : null;
    }

    public function paginate(
        PaginationQuery $pagination,
        ?string $schoolYear = null,
        ?string $semester = null,
        ?string $status = null,
        ?string $search = null
    ): PaginatedResult {
        $conditions = [];
        $params = [];

        if ($schoolYear !== null && trim($schoolYear) !== '') {
            $conditions[] = '`school_year` = :school_year';
            $params[':school_year'] = trim($schoolYear);
        }

        if ($semester !== null && trim($semester) !== '') {
            $conditions[] = '`semester` = :semester';
            $params[':semester'] = trim($semester);
        }

        if ($status !== null && trim($status) !== '') {
            $conditions[] = '`status` = :status';
            $params[':status'] = trim($status);
        }

        if ($search !== null && trim($search) !== '') {
            $conditions[] = '(`section_name` LIKE :search OR `program` LIKE :search_program)';
            $wildcard = '%' . trim($search) . '%';
            $params[':search'] = $wildcard;
            $params[':search_program'] = $wildcard;
        }

        $whereSql = $conditions !== [] ? 'WHERE ' . implode(' AND ', $conditions) : '';

        $countStmt = $this->pdo->prepare("SELECT COUNT(*) FROM `sections` {$whereSql}");
        $countStmt->execute($params);
        $total = (int) $countStmt->fetchColumn();

        $sql = "SELECT s.*, (SELECT COUNT(*) FROM `students` st WHERE st.section_id = s.id) AS enrolled_students_count
                FROM `sections` s
                {$whereSql}
                ORDER BY s.`section_name` ASC
                LIMIT :limit OFFSET :offset";
        $stmt = $this->pdo->prepare($sql);
        foreach ($params as $key => $value) {
            $stmt->bindValue($key, $value, PDO::PARAM_STR);
        }
        $stmt->bindValue(':limit', $pagination->getPerPage(), PDO::PARAM_INT);
        $stmt->bindValue(':offset', $pagination->getOffset(), PDO::PARAM_INT);
        $stmt->execute();

        $rows = $stmt->fetchAll();
        $items = [];
        foreach ($rows as $row) {
            if (is_array($row)) {
                $sectionData = Section::fromDatabaseRow($row)->toArray();
                $sectionData['enrolled_students_count'] = (int) ($row['enrolled_students_count'] ?? 0);
                $items[] = $sectionData;
            }
        }

        return new PaginatedResult(
            $items,
            $pagination->getPage(),
            $pagination->getPerPage(),
            $total
        );
    }

    public function create(Section $section): Section
    {
        $stmt = $this->pdo->prepare(
            'INSERT INTO `sections` (`section_name`, `program`, `year_level`, `school_year`, `semester`, `status`, `term_start_date`)
             VALUES (:section_name, :program, :year_level, :school_year, :semester, :status, :term_start_date)'
        );
        $stmt->execute([
            ':section_name' => $section->getSectionName(),
            ':program' => $section->getProgram(),
            ':year_level' => $section->getYearLevel(),
            ':school_year' => $section->getSchoolYear(),
            ':semester' => $section->getSemester()->value,
            ':status' => $section->getStatus()->value,
            ':term_start_date' => $section->getTermStartDate(),
        ]);

        $id = (int) $this->pdo->lastInsertId();
        return $this->findById($id) ?? $section;
    }

    public function update(Section $section): Section
    {
        $stmt = $this->pdo->prepare(
            'UPDATE `sections`
             SET `section_name` = :section_name,
                 `program` = :program,
                 `year_level` = :year_level,
                 `school_year` = :school_year,
                 `semester` = :semester,
                 `status` = :status,
                 `term_start_date` = :term_start_date
             WHERE `id` = :id'
        );
        $stmt->execute([
            ':id' => $section->getId(),
            ':section_name' => $section->getSectionName(),
            ':program' => $section->getProgram(),
            ':year_level' => $section->getYearLevel(),
            ':school_year' => $section->getSchoolYear(),
            ':semester' => $section->getSemester()->value,
            ':status' => $section->getStatus()->value,
            ':term_start_date' => $section->getTermStartDate(),
        ]);

        return $this->findById((int) $section->getId()) ?? $section;
    }

    public function delete(int $id): bool
    {
        $stmt = $this->pdo->prepare('DELETE FROM `sections` WHERE `id` = :id');
        return $stmt->execute([':id' => $id]);
    }

    public function hasSchedules(int $sectionId): bool
    {
        $stmt = $this->pdo->prepare('SELECT COUNT(*) FROM `schedules` WHERE `section_id` = :id');
        $stmt->execute([':id' => $sectionId]);
        return ((int) $stmt->fetchColumn()) > 0;
    }

    public function hasStudents(int $sectionId): bool
    {
        $stmt = $this->pdo->prepare('SELECT COUNT(*) FROM `students` WHERE `section_id` = :id');
        $stmt->execute([':id' => $sectionId]);
        return ((int) $stmt->fetchColumn()) > 0;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function findStudents(int $sectionId): array
    {
        $stmt = $this->pdo->prepare(
            'SELECT s.id, s.student_number, s.first_name, s.last_name, s.program, u.email, u.status
             FROM `students` s
             INNER JOIN `users` u ON u.id = s.user_id
             WHERE s.section_id = :section_id
             ORDER BY s.last_name ASC, s.first_name ASC'
        );
        $stmt->execute([':section_id' => $sectionId]);
        $rows = $stmt->fetchAll();

        $students = [];
        foreach ($rows as $row) {
            if (is_array($row)) {
                $students[] = [
                    'id' => (int) $row['id'],
                    'student_number' => (string) $row['student_number'],
                    'first_name' => (string) $row['first_name'],
                    'last_name' => (string) $row['last_name'],
                    'full_name' => trim((string) $row['first_name'] . ' ' . (string) $row['last_name']),
                    'program' => (string) $row['program'],
                    'email' => (string) $row['email'],
                    'status' => (string) $row['status'],
                ];
            }
        }

        return $students;
    }
}
