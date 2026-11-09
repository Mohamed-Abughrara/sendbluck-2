<?php
declare(strict_types=1);

namespace MailDesk\Infrastructure\Persistence;

use MailDesk\Domain\Configuration\{Encryption, MailConfiguration, MailConfigurationRepositoryInterface};
use MailDesk\Domain\Shared\EmailAddress;
use PDO;

final class PdoMailConfigurationRepository implements MailConfigurationRepositoryInterface
{
    public function __construct(private PDO $pdo)
    {
    }

    public function getActive(): ?MailConfiguration
    {
        $row = $this->pdo->query('SELECT * FROM mail_configuration ORDER BY id ASC LIMIT 1')->fetch();
        if (!$row) {
            return null;
        }
        return new MailConfiguration(
            (int) $row['id'],
            (string) $row['sender_name'],
            new EmailAddress((string) $row['sender_email']),
            (string) $row['smtp_host'],
            (int) $row['smtp_port'],
            Encryption::from((string) $row['encryption']),
            (string) $row['smtp_username'],
            (string) $row['smtp_password_enc'],
            (bool) $row['is_verified'],
            $row['last_tested_at'] !== null ? (string) $row['last_tested_at'] : null,
        );
    }

    public function save(MailConfiguration $c, int $userId, string $now): void
    {
        if ($c->id === null) {
            $st = $this->pdo->prepare(
                'INSERT INTO mail_configuration
                 (sender_name, sender_email, smtp_host, smtp_port, encryption, smtp_username, smtp_password_enc,
                  is_verified, last_tested_at, updated_by, created_at, updated_at)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
            );
            $st->execute([
                $c->senderName, (string) $c->senderEmail, $c->host, $c->port, $c->encryption->value, $c->username,
                $c->encryptedPassword, (int) $c->verified, $c->lastTestedAt, $userId, $now, $now,
            ]);
            return;
        }
        $st = $this->pdo->prepare(
            'UPDATE mail_configuration SET sender_name = ?, sender_email = ?, smtp_host = ?, smtp_port = ?, encryption = ?,
             smtp_username = ?, smtp_password_enc = ?, is_verified = ?, last_tested_at = ?, updated_by = ?, updated_at = ?
             WHERE id = ?'
        );
        $st->execute([
            $c->senderName, (string) $c->senderEmail, $c->host, $c->port, $c->encryption->value, $c->username,
            $c->encryptedPassword, (int) $c->verified, $c->lastTestedAt, $userId, $now, $c->id,
        ]);
    }

    public function markTested(bool $verified, string $now): void
    {
        $this->pdo->prepare('UPDATE mail_configuration SET is_verified = ?, last_tested_at = ?, updated_at = ?')
            ->execute([(int) $verified, $now, $now]);
    }
}
