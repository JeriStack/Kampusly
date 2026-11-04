<?php

declare(strict_types=1);

namespace Kampusly\Shared\Infrastructure\Http;

use Closure;
use Kampusly\IdentityAccess\Domain\Repository\UserRepositoryInterface;
use Kampusly\Shared\Domain\Exception\UnauthorizedException;

final class AuthenticateMiddleware
{
    private SessionInterface $session;
    private UserRepositoryInterface $userRepository;

    public function __construct(SessionInterface $session, UserRepositoryInterface $userRepository)
    {
        $this->session = $session;
        $this->userRepository = $userRepository;
    }

    /**
     * @param Closure(HttpRequest): JsonResponse $next
     */
    public function handle(HttpRequest $request, Closure $next): JsonResponse
    {
        $userId = $this->session->get('auth_user_id');
        if (!is_int($userId) || $userId < 1) {
            throw new UnauthorizedException();
        }

        $user = $this->userRepository->findById($userId);
        if ($user === null || !$user->isActive()) {
            $this->session->destroy();
            throw new UnauthorizedException(
                $user !== null && !$user->isActive()
                    ? 'Your account has been deactivated. Please contact an administrator.'
                    : 'Session is no longer valid.'
            );
        }

        $requestWithUser = $request->withAttribute('user', $user);

        return $next($requestWithUser);
    }
}

