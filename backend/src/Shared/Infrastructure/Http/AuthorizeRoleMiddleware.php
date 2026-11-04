<?php

declare(strict_types=1);

namespace Kampusly\Shared\Infrastructure\Http;

use Closure;
use Kampusly\IdentityAccess\Domain\Entity\User;
use Kampusly\Shared\Domain\Exception\ForbiddenException;
use Kampusly\Shared\Domain\Exception\UnauthorizedException;

final class AuthorizeRoleMiddleware
{
    /** @var list<string> */
    private array $allowedRoles;

    /**
     * @param list<string> $allowedRoles
     */
    public function __construct(array $allowedRoles)
    {
        $this->allowedRoles = $allowedRoles;
    }

    /**
     * @param Closure(HttpRequest): JsonResponse $next
     */
    public function handle(HttpRequest $request, Closure $next): JsonResponse
    {
        $user = $request->getAttribute('user');
        if (!$user instanceof User) {
            throw new UnauthorizedException();
        }

        if (!in_array($user->getRole()->value, $this->allowedRoles, true)) {
            throw new ForbiddenException(
                sprintf('Access forbidden. Required role: %s.', implode(' or ', $this->allowedRoles))
            );
        }

        return $next($request);
    }
}

