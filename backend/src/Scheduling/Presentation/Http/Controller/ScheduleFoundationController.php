<?php

declare(strict_types=1);

namespace Kampusly\Scheduling\Presentation\Http\Controller;

use Kampusly\Scheduling\Application\Service\ScheduleFoundationService;
use Kampusly\IdentityAccess\Domain\Entity\User;
use Kampusly\Shared\Infrastructure\Http\HttpRequest;
use Kampusly\Shared\Infrastructure\Http\JsonResponse;

final class ScheduleFoundationController
{
    private ScheduleFoundationService $scheduleService;

    public function __construct(ScheduleFoundationService $scheduleService)
    {
        $this->scheduleService = $scheduleService;
    }

    public function listSchedules(HttpRequest $request): JsonResponse
    {
        $result = $this->scheduleService->listSchedules($request->getQueryParams());

        return new JsonResponse(
            $result->toArray(),
            200,
            [],
            $request->getRequestId()
        );
    }

    public function validateConflicts(HttpRequest $request): JsonResponse
    {
        $result = $this->scheduleService->validateProposal($request->getBody());

        return new JsonResponse(
            [
                'data' => $result,
            ],
            200,
            [],
            $request->getRequestId()
        );
    }

    public function createSchedule(HttpRequest $request): JsonResponse
    {
        /** @var User|null $actor */
        $actor = $request->getAttribute('user');
        $schedule = $this->scheduleService->createSchedule(
            $request->getBody(),
            $actor?->getId(),
            $request->getRequestId()
        );

        return new JsonResponse(
            [
                'message' => 'Schedule created successfully.',
                'data' => $schedule->toArray(),
            ],
            201,
            [],
            $request->getRequestId()
        );
    }
}
