<?php

declare(strict_types=1);

namespace Kampusly\Scheduling\Presentation\Http\Controller;

use Kampusly\IdentityAccess\Domain\Entity\User;
use Kampusly\Scheduling\Application\Service\ScheduleChangeRequestService;
use Kampusly\Shared\Domain\Exception\ResourceNotFoundException;
use Kampusly\Shared\Infrastructure\Http\HttpRequest;
use Kampusly\Shared\Infrastructure\Http\JsonResponse;

final class ScheduleChangeRequestController
{
    private ScheduleChangeRequestService $service;

    public function __construct(ScheduleChangeRequestService $service)
    {
        $this->service = $service;
    }

    public function listMine(HttpRequest $request): JsonResponse
    {
        $result = $this->service->listForStudent($this->requireUser($request), $request->getQueryParams());

        return new JsonResponse($result->toArray(), 200, [], $request->getRequestId());
    }

    public function create(HttpRequest $request): JsonResponse
    {
        $created = $this->service->create(
            $this->requireUser($request),
            $request->getParsedBody(),
            $request->getRequestId()
        );

        return new JsonResponse(
            ['message' => 'Schedule change request submitted.', 'data' => $created],
            201,
            [],
            $request->getRequestId()
        );
    }

    public function listAll(HttpRequest $request): JsonResponse
    {
        $result = $this->service->listForAdministrators($request->getQueryParams());

        return new JsonResponse($result->toArray(), 200, [], $request->getRequestId());
    }

    /** @param array<string, string> $params */
    public function review(HttpRequest $request, array $params): JsonResponse
    {
        $reviewed = $this->service->review(
            (int) ($params['id'] ?? 0),
            $this->requireUser($request),
            $request->getParsedBody(),
            $request->getRequestId()
        );

        return new JsonResponse(
            ['message' => 'Schedule change request reviewed.', 'data' => $reviewed],
            200,
            [],
            $request->getRequestId()
        );
    }

    private function requireUser(HttpRequest $request): User
    {
        $user = $request->getAttribute('user');
        if (!$user instanceof User || $user->getId() === null) {
            throw new ResourceNotFoundException('User account was not found.');
        }

        return $user;
    }
}
