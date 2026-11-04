<?php

declare(strict_types=1);

namespace Kampusly\Shared\Infrastructure\Http;

final class CorsMiddleware
{
    private string $allowedOrigin;

    /** @var list<string> */
    private array $allowedMethods;

    /** @var list<string> */
    private array $allowedHeaders;
    private bool $allowCredentials;

    /**
     * @param array<string, mixed> $securityConfig
     */
    public function __construct(array $securityConfig)
    {
        $this->allowedOrigin = rtrim((string) ($securityConfig['frontend_origin'] ?? 'http://localhost:5173'), '/');

        /** @var list<string> $methods */
        $methods = is_array($securityConfig['allowed_methods'] ?? null)
            ? $securityConfig['allowed_methods']
            : ['GET', 'POST', 'PUT', 'PATCH', 'DELETE', 'OPTIONS'];
        $this->allowedMethods = $methods;

        /** @var list<string> $headers */
        $headers = is_array($securityConfig['allowed_headers'] ?? null)
            ? $securityConfig['allowed_headers']
            : ['Content-Type', 'Accept', 'X-CSRF-Token', 'X-Request-Id'];
        $this->allowedHeaders = $headers;

        $this->allowCredentials = (bool) ($securityConfig['allow_credentials'] ?? true);
    }

    public function handlePreflight(HttpRequest $request): ?JsonResponse
    {
        if ($request->getMethod() !== 'OPTIONS') {
            return null;
        }

        $response = new JsonResponse([], 204, [], $request->getRequestId());
        return $this->applyHeaders($request, $response);
    }

    public function applyHeaders(HttpRequest $request, JsonResponse $response): JsonResponse
    {
        $origin = $request->getHeader('origin');
        if ($origin === null || $origin === '') {
            return $response;
        }

        $normalizedOrigin = rtrim($origin, '/');
        $isLocalDev = in_array($normalizedOrigin, ['http://localhost:5173', 'http://127.0.0.1:5173'], true);

        if (!$isLocalDev && $normalizedOrigin !== $this->allowedOrigin) {
            return $response;
        }

        $effectiveOrigin = $isLocalDev ? $normalizedOrigin : $this->allowedOrigin;

        $updated = $response
            ->withHeader('Access-Control-Allow-Origin', $effectiveOrigin)
            ->withHeader('Access-Control-Allow-Methods', implode(', ', $this->allowedMethods))
            ->withHeader('Access-Control-Allow-Headers', implode(', ', $this->allowedHeaders))
            ->withHeader('Vary', 'Origin');

        if ($this->allowCredentials) {
            $updated = $updated->withHeader('Access-Control-Allow-Credentials', 'true');
        }

        return $updated;
    }
}

