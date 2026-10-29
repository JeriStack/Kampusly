<?php

declare(strict_types=1);

namespace Kampusly\IdentityAccess\Presentation\Http\Controller;

use Kampusly\IdentityAccess\Application\Service\UserAdministrationService;
use Kampusly\IdentityAccess\Domain\Entity\User;
use Kampusly\IdentityAccess\Domain\ValueObject\AccountStatus;
use Kampusly\Shared\Domain\Exception\UnauthorizedException;
use Kampusly\Shared\Infrastructure\Http\HttpRequest;
use Kampusly\Shared\Infrastructure\Http\JsonResponse;
use Kampusly\Shared\Infrastructure\Http\RequestValidator;

final class UserController
{
    private UserAdministrationService $userService;

    public function __construct(UserAdministrationService $userService)
    {
        $this->userService = $userService;
    }

    public function listUsers(HttpRequest $request): JsonResponse
    {
        $user = $request->getAttribute('user');
        if (!$user instanceof User) {
            throw new UnauthorizedException();
        }

        $result = $this->userService->listUsers($request->getQueryParams(), $user);

        return new JsonResponse(
            $result->toArray(),
            200,
            [],
            $request->getRequestId()
        );
    }

    /**
     * @param array<string, string> $params
     */
    public function getUser(HttpRequest $request, array $params): JsonResponse
    {
        $user = $request->getAttribute('user');
        if (!$user instanceof User) {
            throw new UnauthorizedException();
        }

        $targetId = (int) ($params['id'] ?? 0);
        $data = $this->userService->getUser($targetId, $user);

        return new JsonResponse(
            ['data' => $data],
            200,
            [],
            $request->getRequestId()
        );
    }

    public function createUser(HttpRequest $request): JsonResponse
    {
        $user = $request->getAttribute('user');
        if (!$user instanceof User) {
            throw new UnauthorizedException();
        }

        $created = $this->userService->createUser(
            $request->getBody(),
            $user,
            $request->getRequestId()
        );

        return new JsonResponse(
            ['data' => $created],
            201,
            [],
            $request->getRequestId()
        );
    }

    /**
     * @param array<string, string> $params
     */
    public function updateStatus(HttpRequest $request, array $params): JsonResponse
    {
        $user = $request->getAttribute('user');
        if (!$user instanceof User) {
            throw new UnauthorizedException();
        }

        $targetId = (int) ($params['id'] ?? 0);
        $body = $request->getBody();

        $validator = new RequestValidator($body);
        $validator->requireIn('status', AccountStatus::values())->validateOrThrow();

        $updated = $this->userService->updateUserStatus(
            $targetId,
            (string) $body['status'],
            $user,
            $request->getRequestId()
        );

        return new JsonResponse(
            ['data' => $updated],
            200,
            [],
            $request->getRequestId()
        );
    }
}

