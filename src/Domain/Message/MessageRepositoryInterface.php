<?php
declare(strict_types=1);

namespace MailDesk\Domain\Message;

interface MessageRepositoryInterface
{
    public function save(Message $message): int;

    public function findById(int $id): ?Message;

    /** @return list<Message> */
    public function list(?MessageStatus $status, string $q, int $page, int $perPage): array;

    public function countByStatus(?MessageStatus $status, string $q = ''): int;

    public function markRetried(int $id): void;

    public function lastSentAt(): ?string;

    /** @return list<Message> */
    public function recent(int $limit): array;
}
