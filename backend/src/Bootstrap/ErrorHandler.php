<?php

declare(strict_types=1);

namespace Kampusly\Bootstrap;

use Kampusly\Shared\Domain\Exception\ApplicationException;
use Kampusly\Shared\Domain\Exception\MethodNotAllowedException;
use Kampusly\Shared\Domain\Exception\RateLimitExceededException;
use Kampusly\Shared\Infrastructure\Http\HttpRequest;
use Kampusly\Shared\Infrastructure\Http\JsonResponse;
use Kampusly\Shared\Infrastructure\Logging\LoggerFactory;
use Throwable;

final class ErrorHandler
{
    private bool $debug;
    private LoggerFactory $logger;

    public function __construct(bool $debug, LoggerFactory $logger)
    {
        $this->debug = $debug;
        $this->logger = $logger;
    }

    public function handle(Throwable $exception, HttpRequest $request): JsonResponse
    {
        $requestId = $request->getRequestId();

        if ($exception instanceof ApplicationException) {
            $status = $exception->getHttpStatus();
            $payload = [
                'code' => $exception->getErrorCode(),
                'message' => $exception->getMessage(),
                'request_id' => $requestId,
            ];

            $fieldErrors = $exception->getFieldErrors();
            if ($fieldErrors !== []) {
                $payload['errors'] = $fieldErrors;
            }

            foreach ($exception->getMetadata() as $metaKey => $metaValue) {
                if (!array_key_exists($metaKey, $payload)) {
                    $payload[$metaKey] = $metaValue;
                }
            }

            $headers = [];
            if ($exception instanceof MethodNotAllowedException) {
                $headers['Allow'] = implode(', ', $exception->getAllowedMethods());
            }
            if ($exception instanceof RateLimitExceededException) {
                $headers['Retry-After'] = (string) $exception->getRetryAfterSeconds();
            }

            if ($status >= 500) {
                $this->logger->error($exception->getMessage(), [
                    'code' => $exception->getErrorCode(),
                    'request_id' => $requestId,
                    'path' => $request->getPath(),
                ]);
            }

            return new JsonResponse($payload, $status, $headers, $requestId);
        }

        $this->logger->error('Unhandled application exception', [
            'exception_class' => $exception::class,
            'message' => $exception->getMessage(),
            'file' => $exception->getFile(),
            'line' => $exception->getLine(),
            'request_id' => $requestId,
            'path' => $request->getPath(),
        ]);

        $payload = [
            'code' => 'INTERNAL_SERVER_ERROR',
            'message' => $this->debug
                ? $exception->getMessage()
                : 'An unexpected internal error occurred. Please try again later.',
            'request_id' => $requestId,
        ];

        return new JsonResponse($payload, 500, [], $requestId);
    }
}
