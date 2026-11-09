<?php
declare(strict_types=1);

namespace MailDesk\Domain\User;

use MailDesk\Domain\Shared\EmailAddress;

interface UserRepositoryInterface
{
    public function findByEmail(EmailAddress $email): ?User;

    public function findById(int $id): ?User;

    public function save(User $user, string $now): int;

    public function updateHash(int $id, string $hash, string $now): void;

    public function touchLogin(int $id, string $now): void;
}
