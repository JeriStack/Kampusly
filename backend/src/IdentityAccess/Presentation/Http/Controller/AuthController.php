<?php

declare(strict_types=1);

namespace Kampusly\IdentityAccess\Presentation\Http\Controller;

use Kampusly\IdentityAccess\Application\Service\AuthenticationService;
use Kampusly\IdentityAccess\Domain\Entity\User;
use Kampusly\Shared\Domain\Exception\UnauthorizedException;
use Kampusly\Shared\Infrastructure\Http\HttpRequest;
use Kampusly\Shared\Infrastructure\Http\JsonResponse;
use Kampusly\Shared\Infrastructure\Http\RateLimiter;
use Kampusly\Shared\Infrastructure\Http\RequestValidator;

final class AuthController
{
    private AuthenticationService $authService;

    public function __construct(AuthenticationService $authService)
    {
        $this->authService = $authService;
    }

    public function login(HttpRequest $request): JsonResponse
    {
        $body = $request->getBody();
        $validator = new RequestValidator($body);
        $validator
            ->requireEmail('email')
            ->requireString('password', 1, 128)
            ->validateOrThrow();

        $clientIp = RateLimiter::resolveClientIp($request);
        $dto = $this->authService->login(
            (string) $body['email'],
            (string) $body['password'],
            $clientIp,
            $request->getRequestId()
        );

        return new JsonResponse(
            ['data' => $dto->toArray()],
            200,
            [],
            $request->getRequestId()
        );
    }

    public function logout(HttpRequest $request): JsonResponse
    {
        $this->authService->logout($request->getRequestId());

        return new JsonResponse(
            [],
            204,
            [],
            $request->getRequestId()
        );
    }

    public function me(HttpRequest $request): JsonResponse
    {
        $dto = $this->authService->getCurrentUser($request->getRequestId());

        return new JsonResponse(
            ['data' => $dto->toArray()],
            200,
            [],
            $request->getRequestId()
        );
    }

    public function changePassword(HttpRequest $request): JsonResponse
    {
        $user = $request->getAttribute('user');
        if (!$user instanceof User || $user->getId() === null) {
            throw new UnauthorizedException();
        }

        $body = $request->getBody();
        $validator = new RequestValidator($body);
        $validator
            ->requireString('current_password', 1, 128)
            ->requireString('new_password', 8, 128)
            ->requireString('new_password_confirmation', 8, 128)
            ->validateOrThrow();

        $clientIp = RateLimiter::resolveClientIp($request);
        $this->authService->changePassword(
            $user->getId(),
            (string) $body['current_password'],
            (string) $body['new_password'],
            (string) $body['new_password_confirmation'],
            $clientIp,
            $request->getRequestId()
        );

        return new JsonResponse(
            ['message' => 'Password changed successfully.'],
            200,
            [],
            $request->getRequestId()
        );
    }
}

