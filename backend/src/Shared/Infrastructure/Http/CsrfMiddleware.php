<?php

declare(strict_types=1);

namespace Kampusly\Shared\Infrastructure\Http;

use Closure;
use Kampusly\Shared\Domain\Exception\ForbiddenException;

final class CsrfMiddleware
{
    private const MUTATING_METHODS = ['POST', 'PUT', 'PATCH', 'DELETE'];

    private CsrfManager $csrfManager;
    /** @var list<string> */
    private array $exemptPaths;

    /**
     * @param list<string> $exemptPaths
     */
    public function __construct(CsrfManager $csrfManager, array $exemptPaths = [])
    {
        $this->csrfManager = $csrfManager;
        $this->exemptPaths = array_merge([
            '/auth/login',
            '/schedules/validate-conflicts',
        ], $exemptPaths);
    }

    /**
     * @param Closure(HttpRequest): JsonResponse $next
     */
    public function handle(HttpRequest $request, Closure $next): JsonResponse
    {
        $method = $request->getMethod();
        if (!in_array($method, self::MUTATING_METHODS, true)) {
            return $next($request);
        }

        $path = $request->getPath();
        foreach ($this->exemptPaths as $exempt) {
            if (str_ends_with($path, $exempt) || str_contains($path, $exempt)) {
                return $next($request);
            }
        }

        $token = $request->getHeader('x-csrf-token');
        if (!$this->csrfManager->validateToken($token)) {
            throw new ForbiddenException(
                'Invalid or missing CSRF token. Please refresh the page and try again.',
                'CSRF_TOKEN_MISMATCH'
            );
        }

        return $next($request);
    }
}

