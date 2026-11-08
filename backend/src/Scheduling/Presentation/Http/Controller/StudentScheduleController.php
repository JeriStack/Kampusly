<?php

declare(strict_types=1);

namespace Kampusly\Scheduling\Presentation\Http\Controller;

use Kampusly\Academic\Domain\Repository\SectionRepositoryInterface;
use Kampusly\Academic\Domain\Repository\StudentRepositoryInterface;
use Kampusly\IdentityAccess\Domain\Entity\User;
use Kampusly\Scheduling\Application\Service\ScheduleFoundationService;
use Kampusly\Shared\Domain\Exception\ResourceNotFoundException;
use Kampusly\Shared\Infrastructure\Http\HttpRequest;
use Kampusly\Shared\Infrastructure\Http\JsonResponse;

final class StudentScheduleController
{
    private StudentRepositoryInterface $students;
    private SectionRepositoryInterface $sections;
    private ScheduleFoundationService $scheduleService;

    public function __construct(
        StudentRepositoryInterface $students,
        SectionRepositoryInterface $sections,
        ScheduleFoundationService $scheduleService
    ) {
        $this->students = $students;
        $this->sections = $sections;
        $this->scheduleService = $scheduleService;
    }

    public function listMySchedule(HttpRequest $request): JsonResponse
    {
        /** @var User|null $user */
        $user = $request->getAttribute('user');
        if ($user === null || $user->getId() === null) {
            throw new ResourceNotFoundException('Student profile was not found.');
        }

        $student = $this->students->findByUserId($user->getId());
        if ($student === null) {
            throw new ResourceNotFoundException('Student profile was not found.');
        }

        $section = $this->sections->findById($student->getSectionId());
        if ($section === null) {
            throw new ResourceNotFoundException('Assigned section was not found.');
        }

        $filters = [
            'section_id' => $student->getSectionId(),
            'school_year' => $section->getSchoolYear(),
            'semester' => $section->getSemester()->value,
        ];
        $schedules = [];
        foreach (['published', 'draft'] as $status) {
            $result = $this->scheduleService->listSchedules($filters + ['status' => $status]);
            array_push($schedules, ...$result->getItems());
        }

        usort(
            $schedules,
            static function (array $first, array $second): int {
                $dayOrder = [
                    'Monday' => 1,
                    'Tuesday' => 2,
                    'Wednesday' => 3,
                    'Thursday' => 4,
                    'Friday' => 5,
                    'Saturday' => 6,
                ];
                $dayComparison = ($dayOrder[$first['day_of_week']] ?? 7)
                    <=> ($dayOrder[$second['day_of_week']] ?? 7);

                return $dayComparison !== 0
                    ? $dayComparison
                    : strcmp((string) $first['start_time'], (string) $second['start_time']);
            }
        );

        return new JsonResponse(
            [
                'data' => [
                    'section' => $section->toArray(),
                    'schedules' => $schedules,
                ],
            ],
            200,
            [],
            $request->getRequestId()
        );
    }
}
