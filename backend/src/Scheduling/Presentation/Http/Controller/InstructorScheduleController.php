<?php

declare(strict_types=1);

namespace Kampusly\Scheduling\Presentation\Http\Controller;

use Kampusly\Academic\Domain\Repository\InstructorRepositoryInterface;
use Kampusly\IdentityAccess\Domain\Entity\User;
use Kampusly\Scheduling\Application\Service\ScheduleFoundationService;
use Kampusly\Shared\Domain\Exception\ResourceNotFoundException;
use Kampusly\Shared\Infrastructure\Http\HttpRequest;
use Kampusly\Shared\Infrastructure\Http\JsonResponse;

final class InstructorScheduleController
{
    private InstructorRepositoryInterface $instructors;
    private ScheduleFoundationService $schedules;

    public function __construct(
        InstructorRepositoryInterface $instructors,
        ScheduleFoundationService $schedules
    ) {
        $this->instructors = $instructors;
        $this->schedules = $schedules;
    }

    public function listMine(HttpRequest $request): JsonResponse
    {
        $user = $request->getAttribute('user');
        if (!$user instanceof User || $user->getId() === null) {
            throw new ResourceNotFoundException('Instructor account was not found.');
        }
        $instructor = $this->instructors->findByUserId($user->getId());
        if ($instructor === null || $instructor->getId() === null) {
            throw new ResourceNotFoundException('Instructor profile was not found.');
        }

        $query = $request->getQueryParams();
        $query['instructor_id'] = $instructor->getId();
        $query['per_page'] = $query['per_page'] ?? 100;
        $result = $this->schedules->listSchedules($query);

        return new JsonResponse($result->toArray(), 200, [], $request->getRequestId());
    }
}
