<?php

declare(strict_types=1);

namespace Kampusly\Shared\Infrastructure\Http;

final class JsonResponse
{
    /** @var array<string, mixed> */
    private array $data;
    private int $statusCode;

    /** @var array<string, string> */
    private array $headers;
    private ?string $requestId;

    /**
     * @param array<string, mixed> $data
     * @param array<string, string> $headers
     */
    public function __construct(
        array $data,
        int $statusCode = 200,
        array $headers = [],
        ?string $requestId = null
    ) {
        $this->data = $data;
        $this->statusCode = $statusCode;
        $this->headers = $headers;
        $this->requestId = $requestId;
    }

    /**
     * @return array<string, mixed>
     */
    public function getData(): array
    {
        return $this->data;
    }

    public function getStatusCode(): int
    {
        return $this->statusCode;
    }

    /**
     * @return array<string, string>
     */
    public function getHeaders(): array
    {
        return $this->headers;
    }

    public function withHeader(string $name, string $value): self
    {
        $clone = clone $this;
        $clone->headers[$name] = $value;
        return $clone;
    }

    public function toJson(): string
    {
        $encoded = json_encode(
            $this->data,
            JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR
        );

        return $encoded;
    }

    public function send(): void
    {
        if (!headers_sent()) {
            http_response_code($this->statusCode);
            header('Content-Type: application/json; charset=utf-8');
            header('X-Content-Type-Options: nosniff');
            header('Cache-Control: no-store, no-cache, must-revalidate');

            if ($this->requestId !== null && $this->requestId !== '') {
                header('X-Request-Id: ' . $this->requestId);
            }

            foreach ($this->headers as $name => $value) {
                header(sprintf('%s: %s', $name, $value));
            }
        }

        if ($this->statusCode === 204) {
            return;
        }

        echo $this->toJson();
    }
}

