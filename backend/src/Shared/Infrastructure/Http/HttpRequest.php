<?php

declare(strict_types=1);

namespace Kampusly\Shared\Infrastructure\Http;

use Kampusly\Shared\Domain\Exception\ValidationException;

final class HttpRequest
{
    private string $method;
    private string $path;

    /** @var array<string, mixed> */
    private array $query;

    /** @var array<string, string> */
    private array $headers;

    /** @var array<string, mixed> */
    private array $body;
    private string $requestId;

    /**
     * @param array<string, mixed> $query
     * @param array<string, string> $headers
     * @param array<string, mixed> $body
     */
    public function __construct(
        string $method,
        string $path,
        array $query = [],
        array $headers = [],
        array $body = [],
        ?string $requestId = null
    ) {
        $this->method = strtoupper($method);
        $this->path = '/' . ltrim($path, '/');
        $this->query = $query;

        $normalizedHeaders = [];
        foreach ($headers as $key => $value) {
            $normalizedHeaders[strtolower($key)] = $value;
        }
        $this->headers = $normalizedHeaders;
        $this->body = $body;
        $this->requestId = $requestId ?? ($normalizedHeaders['x-request-id'] ?? self::generateRequestId());
    }

    public static function fromGlobals(): self
    {
        $method = isset($_SERVER['REQUEST_METHOD']) ? (string) $_SERVER['REQUEST_METHOD'] : 'GET';
        $uri = isset($_SERVER['REQUEST_URI']) ? (string) $_SERVER['REQUEST_URI'] : '/';
        $parsedPath = parse_url($uri, PHP_URL_PATH);
        $path = is_string($parsedPath) && $parsedPath !== '' ? $parsedPath : '/';

        $headers = [];
        foreach ($_SERVER as $key => $value) {
            if (!is_string($value)) {
                continue;
            }
            if (str_starts_with($key, 'HTTP_')) {
                $headerName = strtolower(str_replace('_', '-', substr($key, 5)));
                $headers[$headerName] = $value;
            } elseif (in_array($key, ['CONTENT_TYPE', 'CONTENT_LENGTH'], true)) {
                $headerName = strtolower(str_replace('_', '-', $key));
                $headers[$headerName] = $value;
            }
        }

        $requestId = $headers['x-request-id']
            ?? (isset($_SERVER['REQUEST_ID']) && is_string($_SERVER['REQUEST_ID']) ? $_SERVER['REQUEST_ID'] : null);

        $body = [];
        if (in_array(strtoupper($method), ['POST', 'PUT', 'PATCH', 'DELETE'], true)) {
            $rawBody = file_get_contents('php://input');
            if (is_string($rawBody) && trim($rawBody) !== '') {
                $decoded = json_decode($rawBody, true);
                if (json_last_error() !== JSON_ERROR_NONE || !is_array($decoded)) {
                    throw new ValidationException(
                        ['body' => ['Request body must be a valid JSON object.']],
                        'Malformed JSON request body.'
                    );
                }
                $body = $decoded;
            }
        }

        return new self($method, $path, $_GET, $headers, $body, $requestId);
    }

    public function getMethod(): string
    {
        return $this->method;
    }

    public function getPath(): string
    {
        return $this->path;
    }

    /**
     * @return array<string, mixed>
     */
    public function getQueryParams(): array
    {
        return $this->query;
    }

    public function getHeader(string $name, ?string $default = null): ?string
    {
        $key = strtolower($name);
        return $this->headers[$key] ?? $default;
    }

    /**
     * @return array<string, mixed>
     */
    public function getBody(): array
    {
        return $this->body;
    }

    public function getRequestId(): string
    {
        return $this->requestId;
    }

    private static function generateRequestId(): string
    {
        return 'req_' . bin2hex(random_bytes(8));
    }
}

