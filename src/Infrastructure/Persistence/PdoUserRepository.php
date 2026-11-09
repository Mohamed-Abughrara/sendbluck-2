<?php
declare(strict_types=1);

namespace MailDesk\Infrastructure\Persistence;

use MailDesk\Domain\Shared\EmailAddress;
use MailDesk\Domain\User\{User, UserRepositoryInterface};
use PDO;

final class PdoUserRepository implements UserRepositoryInterface
{
    public function __construct(private PDO $pdo)
    {
    }

    public function findByEmail(EmailAddress $email): ?User
    {
        $st = $this->pdo->prepare('SELECT * FROM users WHERE email = ?');
        $st->execute([(string) $email]);
        $row = $st->fetch();
        return $row ? $this->hydrate($row) : null;
    }

    public function findById(int $id): ?User
    {
        $st = $this->pdo->prepare('SELECT * FROM users WHERE id = ?');
        $st->execute([$id]);
        $row = $st->fetch();
        return $row ? $this->hydrate($row) : null;
    }

    public function save(User $user, string $now): int
    {
        $st = $this->pdo->prepare('INSERT INTO users (name, email, password_hash, created_at, updated_at) VALUES (?, ?, ?, ?, ?)');
        $st->execute([$user->name, (string) $user->email, $user->passwordHash, $now, $now]);
        return (int) $this->pdo->lastInsertId();
    }

    public function updateHash(int $id, string $hash, string $now): void
    {
        $this->pdo->prepare('UPDATE users SET password_hash = ?, updated_at = ? WHERE id = ?')->execute([$hash, $now, $id]);
    }

    public function touchLogin(int $id, string $now): void
    {
        $this->pdo->prepare('UPDATE users SET last_login_at = ? WHERE id = ?')->execute([$now, $id]);
    }

    /** @param array<string,mixed> $row */
    private function hydrate(array $row): User
    {
        return new User((int) $row['id'], (string) $row['name'], new EmailAddress((string) $row['email']), (string) $row['password_hash']);
    }
}
