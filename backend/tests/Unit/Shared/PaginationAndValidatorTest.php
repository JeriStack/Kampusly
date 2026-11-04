<?php

declare(strict_types=1);

namespace Kampusly\Tests\Unit\Shared;

use Kampusly\Shared\Application\DTO\PaginatedResult;
use Kampusly\Shared\Domain\Exception\BadRequestException;
use Kampusly\Shared\Domain\Exception\ConflictException;
use Kampusly\Shared\Domain\Exception\ForbiddenException;
use Kampusly\Shared\Domain\Exception\RateLimitExceededException;
use Kampusly\Shared\Domain\Exception\UnauthorizedException;
use Kampusly\Shared\Domain\Exception\UnsupportedMediaTypeException;
use Kampusly\Shared\Domain\Exception\ValidationException;
use Kampusly\Shared\Domain\ValueObject\PaginationQuery;
use Kampusly\Shared\Infrastructure\Http\RequestValidator;
use Kampusly\Shared\Infrastructure\Logging\LoggerFactory;
use Kampusly\Tests\Support\TestCase;

final class PaginationAndValidatorTest extends TestCase
{
    public function testPaginationQueryDefaultAndOffsetCalculation(): void
    {
        $query = new PaginationQuery(1, 20);
        $this->assertSame(1, $query->getPage());
        $this->assertSame(20, $query->getPerPage());
        $this->assertSame(0, $query->getOffset());

        $page3 = new PaginationQuery(3, 15);
        $this->assertSame(30, $page3->getOffset());
    }

    public function testPaginationQueryRejectsNegativeOrExcessivePage(): void
    {
        $this->assertThrows(ValidationException::class, static function (): void {
            new PaginationQuery(0, 20);
        });

        $this->assertThrows(ValidationException::class, static function (): void {
            new PaginationQuery(1, 101); // Exceeds MAX_PER_PAGE (100)
        });
    }

    public function testPaginatedResultCalculatesTotalPagesAndStructure(): void
    {
        $result = new PaginatedResult([['id' => 1], ['id' => 2]], 1, 10, 25);
        $this->assertSame(3, $result->getTotalPages());
        $serialized = $result->toArray();

        $this->assertArrayHasKey('data', $serialized);
        $this->assertArrayHasKey('meta', $serialized);
        $this->assertSame(25, $serialized['meta']['total']);
        $this->assertSame(3, $serialized['meta']['total_pages']);
    }

    public function testRequestValidatorDateAndTimeValidation(): void
    {
        $validInput = [
            'date' => '2026-11-09',
            'start' => '08:30',
            'end' => '10:00',
            'year' => '2026-2027',
        ];

        $validator = new RequestValidator($validInput);
        $validator
            ->requireDate('date')
            ->requireTime('start')
            ->requireTime('end')
            ->requireTimeOrder('start', 'end')
            ->requireSchoolYear('year')
            ->validateOrThrow();

        $this->assertCount(0, $validator->getErrors());
    }

    public function testRequestValidatorRejectsMalformedValues(): void
    {
        $invalidInput = [
            'date' => '2026-02-31', // Invalid day of February
            'start' => '10:00',
            'end' => '09:00', // start is later than end
            'year' => '2026-2028', // Non-consecutive years
        ];

        $validator = new RequestValidator($invalidInput);
        $validator
            ->requireDate('date')
            ->requireTimeOrder('start', 'end')
            ->requireSchoolYear('year');

        $errors = $validator->getErrors();
        $this->assertArrayHasKey('date', $errors);
        $this->assertArrayHasKey('end', $errors);
        $this->assertArrayHasKey('year', $errors);

        $this->assertThrows(ValidationException::class, static function () use ($validator): void {
            $validator->validateOrThrow();
        });
    }

    public function testApplicationExceptionsStatusAndCodes(): void
    {
        $badReq = new BadRequestException();
        $this->assertSame(400, $badReq->getHttpStatus());
        $this->assertSame('BAD_REQUEST', $badReq->getErrorCode());

        $unauth = new UnauthorizedException();
        $this->assertSame(401, $unauth->getHttpStatus());
        $this->assertSame('UNAUTHENTICATED', $unauth->getErrorCode());

        $forbidden = new ForbiddenException();
        $this->assertSame(403, $forbidden->getHttpStatus());
        $this->assertSame('FORBIDDEN', $forbidden->getErrorCode());

        $conflict = ConflictException::scheduleConflict([['dimension' => 'room']]);
        $this->assertSame(409, $conflict->getHttpStatus());
        $this->assertSame('SCHEDULE_CONFLICT', $conflict->getErrorCode());
        $this->assertCount(1, $conflict->getConflicts());

        $mediaType = new UnsupportedMediaTypeException();
        $this->assertSame(415, $mediaType->getHttpStatus());
        $this->assertSame('UNSUPPORTED_MEDIA_TYPE', $mediaType->getErrorCode());

        $rateLimit = new RateLimitExceededException(45);
        $this->assertSame(429, $rateLimit->getHttpStatus());
        $this->assertSame('RATE_LIMITED', $rateLimit->getErrorCode());
        $this->assertSame(45, $rateLimit->getRetryAfterSeconds());
    }

    public function testSecretRedactionInLoggerFactory(): void
    {
        $logger = new LoggerFactory('storage/logs/test.log');
        $sanitized = $logger->redactSecrets([
            'email' => 'user@kampusly.local',
            'password' => 'SuperSecret123!',
            'db_password' => 'db_secret',
            'token' => 'jwt_token_here',
            'nested' => [
                'password_hash' => '$2y$10$...',
                'ok_field' => 'visible',
            ],
        ]);

        $this->assertSame('user@kampusly.local', $sanitized['email']);
        $this->assertSame('[REDACTED]', $sanitized['password']);
        $this->assertSame('[REDACTED]', $sanitized['db_password']);
        $this->assertSame('[REDACTED]', $sanitized['token']);
        $this->assertSame('[REDACTED]', $sanitized['nested']['password_hash']);
        $this->assertSame('visible', $sanitized['nested']['ok_field']);
    }
}

