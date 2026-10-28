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

        $sql = "SELECT * FROM `sections` {$whereSql} ORDER BY `section_name` ASC LIMIT :limit OFFSET :offset";
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
                $items[] = Section::fromDatabaseRow($row)->toArray();
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
            'INSERT INTO `sections` (`section_name`, `program`, `year_level`, `school_year`, `semester`, `status`)
             VALUES (:section_name, :program, :year_level, :school_year, :semester, :status)'
        );
        $stmt->execute([
            ':section_name' => $section->getSectionName(),
            ':program' => $section->getProgram(),
            ':year_level' => $section->getYearLevel(),
            ':school_year' => $section->getSchoolYear(),
            ':semester' => $section->getSemester()->value,
            ':status' => $section->getStatus()->value,
        ]);

        $id = (int) $this->pdo->lastInsertId();
        return $this->findById($id) ?? $section;
    }
}

