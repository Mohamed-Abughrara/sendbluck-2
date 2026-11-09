<?php
declare(strict_types=1);

namespace MailDesk\Infrastructure\Logging;

use MailDesk\Application\Port\AuditLoggerInterface;
use PDO;

final class PdoAuditLogger implements AuditLoggerInterface
{
    public function __construct(private PDO $pdo, private ?FileErrorLogger $errors = null)
    {
    }

    public function log(?int $userId, string $action, array $context = []): void
    {
        try {
            $st = $this->pdo->prepare(
                'INSERT INTO audit_log (user_id, action, context, ip, user_agent, created_at) VALUES (?, ?, ?, ?, ?, ?)'
            );
            $st->execute([
                $userId,
                $action,
                $context === [] ? null : json_encode($context, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR),
                isset($_SERVER['REMOTE_ADDR']) ? substr((string) $_SERVER['REMOTE_ADDR'], 0, 45) : null,
                isset($_SERVER['HTTP_USER_AGENT']) ? substr((string) $_SERVER['HTTP_USER_AGENT'], 0, 255) : null,
                gmdate('Y-m-d H:i:s'),
            ]);
        } catch (\Throwable $e) {
            $this->errors?->error('audit log write failed', ['action' => $action, 'exception' => $e->getMessage()]);
        }
    }
}
