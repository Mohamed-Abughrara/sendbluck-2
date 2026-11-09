<?php
declare(strict_types=1);

namespace MailDesk\Application\Port;

interface AuditLoggerInterface
{
    /** @param array<string,mixed> $context Never include secret values. */
    public function log(?int $userId, string $action, array $context = []): void;
}
