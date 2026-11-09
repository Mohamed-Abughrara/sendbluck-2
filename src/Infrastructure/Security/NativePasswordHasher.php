<?php
declare(strict_types=1);

namespace MailDesk\Infrastructure\Security;

use MailDesk\Application\Port\PasswordHasherInterface;

final class NativePasswordHasher implements PasswordHasherInterface
{
    private string|int $algo;

    public function __construct()
    {
        $this->algo = defined('PASSWORD_ARGON2ID') ? PASSWORD_ARGON2ID : PASSWORD_BCRYPT;
    }

    public function hash(string $plain): string
    {
        return password_hash($plain, $this->algo);
    }

    public function verify(string $plain, string $hash): bool
    {
        return password_verify($plain, $hash);
    }

    public function needsRehash(string $hash): bool
    {
        return password_needs_rehash($hash, $this->algo);
    }
}
