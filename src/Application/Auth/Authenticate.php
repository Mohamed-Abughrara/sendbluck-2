<?php
declare(strict_types=1);

namespace MailDesk\Application\Auth;

use MailDesk\Application\Port\{AuditLoggerInterface, ClockInterface, PasswordHasherInterface, RateLimiterInterface};
use MailDesk\Domain\Exception\{InvalidCredentials, InvalidEmailAddress, RateLimited};
use MailDesk\Domain\Shared\EmailAddress;
use MailDesk\Domain\User\{User, UserRepositoryInterface};

final class Authenticate
{
    public function __construct(
        private UserRepositoryInterface $users,
        private PasswordHasherInterface $hasher,
        private RateLimiterInterface $limiter,
        private AuditLoggerInterface $audit,
        private ClockInterface $clock,
        private int $maxAttempts = 5,
        private int $windowSeconds = 900,
    ) {
    }

    /**
     * @throws RateLimited
     * @throws InvalidCredentials
     */
    public function execute(string $email, string $password, string $ip): User
    {
        $emailKey = 'login:email:' . hash('sha256', strtolower(trim($email)));
        $ipKey = 'login:ip:' . $ip;

        $wait = max(
            $this->limiter->peek($emailKey, $this->maxAttempts, $this->windowSeconds),
            $this->limiter->peek($ipKey, $this->maxAttempts, $this->windowSeconds),
        );
        if ($wait > 0) {
            throw new RateLimited($wait);
        }

        $user = null;
        try {
            $user = $this->users->findByEmail(new EmailAddress($email));
        } catch (InvalidEmailAddress) {
            // fall through: treated like unknown user
        }

        // Always run a hash verification so timing does not reveal whether the user exists.
        $hash = $user?->passwordHash ?? '$2y$10$usesomesillystringforsaltuQYqVwKnSc1Vv0pZ7F7nKr0m3JdHm';
        $ok = $this->hasher->verify($password, $hash) && $user !== null;

        if (!$ok) {
            $this->limiter->hit($emailKey, $this->windowSeconds);
            $this->limiter->hit($ipKey, $this->windowSeconds);
            $this->audit->log($user?->id, 'auth.login_failed', []);
            throw new InvalidCredentials();
        }

        $this->limiter->clear($emailKey);
        $now = $this->clock->now()->format('Y-m-d H:i:s');
        if ($this->hasher->needsRehash($user->passwordHash)) {
            $this->users->updateHash((int) $user->id, $this->hasher->hash($password), $now);
        }
        $this->users->touchLogin((int) $user->id, $now);
        $this->audit->log($user->id, 'auth.login', []);

        return $user;
    }
}
