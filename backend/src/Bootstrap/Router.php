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
     * @var list<array{
     *   method: string,
     *   pattern: string,
     *   regex: string,
     *   paramNames: list<string>,
     *   handler: Closure(HttpRequest, array<string, string>): JsonResponse
     * }>
     */
    private array $routes = [];

    public function __construct(string $prefix = '/api/v1')
    {
        $this->prefix = '/' . trim($prefix, '/');
    }

    /**
     * @param Closure(HttpRequest, array<string, string>): JsonResponse $handler
     */
    public function get(string $path, Closure $handler): void
    {
        $this->addRoute('GET', $path, $handler);
    }

    /**
     * @param Closure(HttpRequest, array<string, string>): JsonResponse $handler
     */
    public function post(string $path, Closure $handler): void
    {
        $this->addRoute('POST', $path, $handler);
    }

    /**
     * @param Closure(HttpRequest, array<string, string>): JsonResponse $handler
     */
    public function put(string $path, Closure $handler): void
    {
        $this->addRoute('PUT', $path, $handler);
    }

    /**
     * @param Closure(HttpRequest, array<string, string>): JsonResponse $handler
     */
    public function patch(string $path, Closure $handler): void
    {
        $this->addRoute('PATCH', $path, $handler);
    }

    /**
     * @param Closure(HttpRequest, array<string, string>): JsonResponse $handler
     */
    public function delete(string $path, Closure $handler): void
    {
        $this->addRoute('DELETE', $path, $handler);
    }

    /**
     * @param Closure(HttpRequest, array<string, string>): JsonResponse $handler
     */
    public function addRoute(string $method, string $path, Closure $handler): void
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
        ];
    }

    public function dispatch(HttpRequest $request): JsonResponse
    {
        $method = $request->getMethod();
        $path = $this->normalizePath($request->getPath());
        $allowedForPath = [];

        foreach ($this->routes as $route) {
            if (preg_match($route['regex'], $path, $matches) !== 1) {
                continue;
            }

            if ($route['method'] !== $method) {
                $allowedForPath[] = $route['method'];
                continue;
            }

            $params = [];
            foreach ($route['paramNames'] as $name) {
                if (isset($matches[$name])) {
                    $params[$name] = $matches[$name];
                }
            }

            $handler = $route['handler'];
            return $handler($request, $params);
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

