<?php

declare(strict_types=1);

namespace Kampusly\IdentityAccess\Application\Service;

use Kampusly\IdentityAccess\Application\DTO\AuthenticatedUserDTO;
use Kampusly\IdentityAccess\Domain\Repository\UserRepositoryInterface;
use Kampusly\IdentityAccess\Domain\Service\PasswordHasher;
use Kampusly\Shared\Application\Contract\AuditLoggerInterface;
use Kampusly\Shared\Domain\Exception\ForbiddenException;
use Kampusly\Shared\Domain\Exception\UnauthorizedException;
use Kampusly\Shared\Domain\Exception\ValidationException;
use Kampusly\Shared\Infrastructure\Http\CsrfManager;
use Kampusly\Shared\Infrastructure\Http\RateLimiter;
use Kampusly\Shared\Infrastructure\Http\SessionInterface;

final class AuthenticationService
{
    private UserRepositoryInterface $userRepository;
    private PasswordHasher $passwordHasher;
    private SessionInterface $session;
    private CsrfManager $csrfManager;
    private RateLimiter $rateLimiter;
    private AuditLoggerInterface $auditLogger;

    public function __construct(
        UserRepositoryInterface $userRepository,
        PasswordHasher $passwordHasher,
        SessionInterface $session,
        CsrfManager $csrfManager,
        RateLimiter $rateLimiter,
        AuditLoggerInterface $auditLogger
    ) {
        $this->userRepository = $userRepository;
        $this->passwordHasher = $passwordHasher;
        $this->session = $session;
        $this->csrfManager = $csrfManager;
        $this->rateLimiter = $rateLimiter;
        $this->auditLogger = $auditLogger;
    }

    public function login(
        string $email,
        string $password,
        string $clientIp,
        ?string $requestId = null
    ): AuthenticatedUserDTO {
        $normalizedEmail = strtolower(trim($email));
        $rateLimitKey = sprintf('login:ip:%s:email:%s', $clientIp, $normalizedEmail);

        // Enforce rate limit (5 attempts per 60 seconds)
        $this->rateLimiter->enforce($rateLimitKey, 5, 60);

        $user = $this->userRepository->findByEmail($normalizedEmail);

        if ($user === null || !$this->passwordHasher->verify($password, $user->getPasswordHash())) {
            $this->rateLimiter->hit($rateLimitKey, 60);
            $this->auditLogger->record(
                null,
                'auth.login.failed',
                'user',
                $user?->getId(),
                ['email' => $normalizedEmail, 'ip' => $clientIp],
                $requestId
            );

            throw new UnauthorizedException('Invalid email or password.');
        }

        if (!$user->isActive()) {
            $this->auditLogger->record(
                $user->getId(),
                'auth.login.inactive',
                'user',
                $user->getId(),
                ['email' => $normalizedEmail, 'ip' => $clientIp],
                $requestId
            );

            throw new ForbiddenException(
                'Your account has been deactivated. Please contact an administrator.',
                'ACCOUNT_INACTIVE'
            );
        }

        // Rehash password if algorithm cost has been upgraded
        if ($this->passwordHasher->needsRehash($user->getPasswordHash())) {
            $this->userRepository->updatePassword(
                $user->getId() ?? 0,
                $this->passwordHasher->hash($password)
            );
        }

        // Reset rate limit on successful authentication
        $this->rateLimiter->reset($rateLimitKey);

        // Regenerate session ID upon privilege change to prevent session fixation
        $this->session->regenerateId(true);
        $this->session->set('auth_user_id', $user->getId());
        $this->session->set('auth_user_role', $user->getRole()->value);
        $this->session->set('login_at', time());

        $csrfToken = $this->csrfManager->regenerateToken();

        $this->userRepository->updateLastLogin($user->getId() ?? 0);

        $studentProfile = $this->userRepository->findStudentProfile($user->getId() ?? 0);
        $instructorProfile = $this->userRepository->findInstructorProfile($user->getId() ?? 0);

        $this->auditLogger->record(
            $user->getId(),
            'auth.login.success',
            'user',
            $user->getId(),
            ['role' => $user->getRole()->value, 'ip' => $clientIp],
            $requestId
        );

        return AuthenticatedUserDTO::fromUser($user, $csrfToken, $studentProfile, $instructorProfile);
    }

    public function logout(?string $requestId = null): void
    {
        $userId = $this->session->get('auth_user_id');
        if (is_int($userId) && $userId > 0) {
            $this->auditLogger->record(
                $userId,
                'auth.logout',
                'user',
                $userId,
                [],
                $requestId
            );
        }

        $this->session->destroy();
    }

    public function getCurrentUser(?string $requestId = null): AuthenticatedUserDTO
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

        $csrfToken = $this->csrfManager->getToken();
        $studentProfile = $this->userRepository->findStudentProfile($user->getId() ?? 0);
        $instructorProfile = $this->userRepository->findInstructorProfile($user->getId() ?? 0);

        return AuthenticatedUserDTO::fromUser($user, $csrfToken, $studentProfile, $instructorProfile);
    }

    public function changePassword(
        int $userId,
        string $currentPassword,
        string $newPassword,
        string $newPasswordConfirmation,
        string $clientIp,
        ?string $requestId = null
    ): void {
        $rateLimitKey = sprintf('change_password:user:%d', $userId);
        $this->rateLimiter->enforce($rateLimitKey, 5, 300);

        $user = $this->userRepository->findById($userId);
        if ($user === null || !$user->isActive()) {
            throw new UnauthorizedException();
        }

        if (!$this->passwordHasher->verify($currentPassword, $user->getPasswordHash())) {
            $this->rateLimiter->hit($rateLimitKey, 300);
            throw new UnauthorizedException('Current password does not match.');
        }

        if ($newPassword !== $newPasswordConfirmation) {
            throw new ValidationException([
                'new_password_confirmation' => ['Password confirmation does not match.'],
            ]);
        }

        $policyErrors = $this->passwordHasher->validatePolicy($newPassword);
        if ($policyErrors !== []) {
            throw new ValidationException(['new_password' => $policyErrors]);
        }

        $newHash = $this->passwordHasher->hash($newPassword);
        $this->userRepository->updatePassword($userId, $newHash);

        // Rotate session ID after password change
        $this->session->regenerateId(true);
        $this->rateLimiter->reset($rateLimitKey);

        $this->auditLogger->record(
            $userId,
            'auth.password_changed',
            'user',
            $userId,
            ['ip' => $clientIp],
            $requestId
        );
    }
}

