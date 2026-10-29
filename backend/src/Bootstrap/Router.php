<?php

declare(strict_types=1);

namespace Kampusly\Bootstrap;

use Closure;
use Kampusly\Shared\Domain\Exception\MethodNotAllowedException;
use Kampusly\Shared\Domain\Exception\ResourceNotFoundException;
use Kampusly\Shared\Infrastructure\Http\HttpRequest;
use Kampusly\Shared\Infrastructure\Http\JsonResponse;

final class Router
{
    private string $prefix;

    /**
     * @var list<Closure(HttpRequest, Closure(HttpRequest): JsonResponse): JsonResponse>
     */
    private array $globalMiddleware = [];

    /**
     * @var list<array{
     *   method: string,
     *   pattern: string,
     *   regex: string,
     *   paramNames: list<string>,
     *   handler: Closure(HttpRequest, array<string, string>): JsonResponse,
     *   middleware: list<Closure(HttpRequest, Closure(HttpRequest): JsonResponse): JsonResponse>
     * }>
     */
    private array $routes = [];

    public function __construct(string $prefix = '/api/v1')
    {
        $this->prefix = '/' . trim($prefix, '/');
    }

    /**
     * Register a global middleware applied to every matched route.
     *
     * @param Closure(HttpRequest, Closure(HttpRequest): JsonResponse): JsonResponse $middleware
     */
    public function addMiddleware(Closure $middleware): void
    {
        $this->globalMiddleware[] = $middleware;
    }

    /**
     * @param Closure(HttpRequest, array<string, string>): JsonResponse $handler
     * @param list<Closure(HttpRequest, Closure(HttpRequest): JsonResponse): JsonResponse> $middleware
     */
    public function get(string $path, Closure $handler, array $middleware = []): void
    {
        $this->addRoute('GET', $path, $handler, $middleware);
    }

    /**
     * @param Closure(HttpRequest, array<string, string>): JsonResponse $handler
     * @param list<Closure(HttpRequest, Closure(HttpRequest): JsonResponse): JsonResponse> $middleware
     */
    public function post(string $path, Closure $handler, array $middleware = []): void
    {
        $this->addRoute('POST', $path, $handler, $middleware);
    }

    /**
     * @param Closure(HttpRequest, array<string, string>): JsonResponse $handler
     * @param list<Closure(HttpRequest, Closure(HttpRequest): JsonResponse): JsonResponse> $middleware
     */
    public function put(string $path, Closure $handler, array $middleware = []): void
    {
        $this->addRoute('PUT', $path, $handler, $middleware);
    }

    /**
     * @param Closure(HttpRequest, array<string, string>): JsonResponse $handler
     * @param list<Closure(HttpRequest, Closure(HttpRequest): JsonResponse): JsonResponse> $middleware
     */
    public function patch(string $path, Closure $handler, array $middleware = []): void
    {
        $this->addRoute('PATCH', $path, $handler, $middleware);
    }

    /**
     * @param Closure(HttpRequest, array<string, string>): JsonResponse $handler
     * @param list<Closure(HttpRequest, Closure(HttpRequest): JsonResponse): JsonResponse> $middleware
     */
    public function delete(string $path, Closure $handler, array $middleware = []): void
    {
        $this->addRoute('DELETE', $path, $handler, $middleware);
    }

    /**
     * @param Closure(HttpRequest, array<string, string>): JsonResponse $handler
     * @param list<Closure(HttpRequest, Closure(HttpRequest): JsonResponse): JsonResponse> $middleware
     */
    public function addRoute(string $method, string $path, Closure $handler, array $middleware = []): void
    {
        $normalizedPath = $this->normalizePath($this->prefix . '/' . ltrim($path, '/'));
        if ($path === '' || $path === '/') {
            $normalizedPath = $this->normalizePath($this->prefix);
        }

        $paramNames = [];
        $regexPattern = preg_replace_callback(
            '/\{([a-zA-Z_][a-zA-Z0-9_]*)\}/',
            static function (array $matches) use (&$paramNames): string {
                $paramNames[] = $matches[1];
                return '(?P<' . $matches[1] . '>[^/]+)';
            },
            $normalizedPath
        );

        $regex = '#^' . $regexPattern . '$#';

        $this->routes[] = [
            'method' => strtoupper($method),
            'pattern' => $normalizedPath,
            'regex' => $regex,
            'paramNames' => $paramNames,
            'handler' => $handler,
            'middleware' => $middleware,
        ];
    }

    public function dispatch(HttpRequest $request): JsonResponse
    {
        $method = $request->getMethod();
        $path = $this->normalizePath($request->getPath());
        $allowedForPath = [];

        $pathsToTry = [$path];
        if (!str_starts_with($path, $this->prefix . '/') && $path !== $this->prefix) {
            $pathsToTry[] = $this->normalizePath($this->prefix . $path);
        }

        foreach ($pathsToTry as $candidatePath) {
            foreach ($this->routes as $route) {
                if (preg_match($route['regex'], $candidatePath, $matches) !== 1) {
                    continue;
                }

                if ($route['method'] !== $method) {
                    $allowedForPath[] = $route['method'];
                    continue;
                }

                $request->enforceJsonContentType();

                $params = [];
                foreach ($route['paramNames'] as $name) {
                    if (isset($matches[$name])) {
                        $params[$name] = $matches[$name];
                    }
                }

                $handler = $route['handler'];
                $corePipeline = static fn (HttpRequest $req): JsonResponse => $handler($req, $params);

                $pipeline = array_merge($this->globalMiddleware, $route['middleware']);
                $next = $corePipeline;
                for ($i = count($pipeline) - 1; $i >= 0; $i--) {
                    $mw = $pipeline[$i];
                    $currentNext = $next;
                    $next = static fn (HttpRequest $req): JsonResponse => $mw($req, $currentNext);
                }

                return $next($request);
            }
        }

        if ($allowedForPath !== []) {
            $uniqueAllowed = array_values(array_unique($allowedForPath));
            throw new MethodNotAllowedException($uniqueAllowed);
        }

        throw new ResourceNotFoundException(
            sprintf('No API route matched "%s %s".', $method, $path)
        );
    }

    private function normalizePath(string $path): string
    {
        $trimmed = '/' . trim($path, '/');
        return $trimmed === '//' ? '/' : $trimmed;
    }
}
