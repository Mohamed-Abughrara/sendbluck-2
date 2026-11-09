<?php
declare(strict_types=1);

namespace MailDesk\Infrastructure\Persistence;

use MailDesk\Domain\Message\{Message, MessageRepositoryInterface, MessageStatus};
use PDO;

final class PdoMessageRepository implements MessageRepositoryInterface
{
    public function __construct(private PDO $pdo)
    {
    }

    public function save(Message $m): int
    {
        $st = $this->pdo->prepare(
            'INSERT INTO messages (user_id, recipients, recipient_count, subject, body, status, error, sent_at, created_at)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)'
        );
        $sent = $m->sentAt->setTimezone(new \DateTimeZone('UTC'))->format('Y-m-d H:i:s');
        $st->execute([
            $m->userId,
            json_encode(['to' => $m->to, 'cc' => $m->cc], JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR),
            $m->recipientCount(),
            $m->subject,
            $m->bodyHtml,
            $m->status->value,
            $m->error !== null ? mb_substr($m->error, 0, 500) : null,
            $sent,
            $sent,
        ]);
        return (int) $this->pdo->lastInsertId();
    }

    public function findById(int $id): ?Message
    {
        $st = $this->pdo->prepare('SELECT * FROM messages WHERE id = ?');
        $st->execute([$id]);
        $row = $st->fetch();
        return $row ? $this->hydrate($row) : null;
    }

    public function list(?MessageStatus $status, string $q, int $page, int $perPage): array
    {
        [$where, $params] = $this->filter($status, $q);
        $st = $this->pdo->prepare("SELECT * FROM messages $where ORDER BY sent_at DESC, id DESC LIMIT ? OFFSET ?");
        $i = 1;
        foreach ($params as $p) {
            $st->bindValue($i++, $p);
        }
        $st->bindValue($i++, $perPage, PDO::PARAM_INT);
        $st->bindValue($i, max(0, ($page - 1) * $perPage), PDO::PARAM_INT);
        $st->execute();
        return array_map($this->hydrate(...), $st->fetchAll());
    }

    public function countByStatus(?MessageStatus $status, string $q = ''): int
    {
        [$where, $params] = $this->filter($status, $q);
        $st = $this->pdo->prepare("SELECT COUNT(*) FROM messages $where");
        $st->execute($params);
        return (int) $st->fetchColumn();
    }

    public function markRetried(int $id): void
    {
        $this->pdo->prepare('UPDATE messages SET retried_at = ? WHERE id = ?')->execute([gmdate('Y-m-d H:i:s'), $id]);
    }

    public function lastSentAt(): ?string
    {
        $v = $this->pdo->query("SELECT MAX(sent_at) FROM messages WHERE status = 'sent'")->fetchColumn();
        return $v !== false && $v !== null ? (string) $v : null;
    }

    public function recent(int $limit): array
    {
        $st = $this->pdo->prepare('SELECT * FROM messages ORDER BY sent_at DESC, id DESC LIMIT ?');
        $st->bindValue(1, $limit, PDO::PARAM_INT);
        $st->execute();
        return array_map($this->hydrate(...), $st->fetchAll());
    }

    /** @return array{string, list<string>} */
    private function filter(?MessageStatus $status, string $q): array
    {
        $conds = [];
        $params = [];
        if ($status !== null) {
            $conds[] = 'status = ?';
            $params[] = $status->value;
        }
        if ($q !== '') {
            $like = '%' . str_replace(['!', '%', '_'], ['!!', '!%', '!_'], $q) . '%';
            $conds[] = "(subject LIKE ? ESCAPE '!' OR recipients LIKE ? ESCAPE '!')";
            $params[] = $like;
            $params[] = $like;
        }
        return [$conds === [] ? '' : 'WHERE ' . implode(' AND ', $conds), $params];
    }

    /** @param array<string,mixed> $row */
    private function hydrate(array $row): Message
    {
        $r = json_decode((string) $row['recipients'], true) ?: [];
        return new Message(
            (int) $row['id'],
            (int) $row['user_id'],
            $r['to'] ?? [],
            $r['cc'] ?? [],
            (string) $row['subject'],
            (string) $row['body'],
            MessageStatus::from((string) $row['status']),
            $row['error'] !== null ? (string) $row['error'] : null,
            new \DateTimeImmutable((string) $row['sent_at'], new \DateTimeZone('UTC')),
            $row['retried_at'] !== null,
        );
    }
}
