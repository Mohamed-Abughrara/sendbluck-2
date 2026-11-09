<?php
declare(strict_types=1);

namespace MailDesk\Application\Mail;

use MailDesk\Domain\Message\{Message, MessageRepositoryInterface, MessageStatus};

final class ListMessages
{
    public function __construct(private MessageRepositoryInterface $messages)
    {
    }

    /** @return array{items: list<Message>, total: int, page: int, pages: int} */
    public function execute(MessageStatus $status, string $q, int $page, int $perPage = 25): array
    {
        $page = max(1, $page);
        $q = trim($q);
        $total = $this->messages->countByStatus($status, $q);
        return [
            'items' => $this->messages->list($status, $q, $page, $perPage),
            'total' => $total,
            'page' => $page,
            'pages' => max(1, (int) ceil($total / $perPage)),
        ];
    }

    /** @return array{sent: int, failed: int} */
    public function counts(): array
    {
        return [
            'sent' => $this->messages->countByStatus(MessageStatus::Sent),
            'failed' => $this->messages->countByStatus(MessageStatus::Failed),
        ];
    }

    public function find(int $id): ?Message
    {
        return $this->messages->findById($id);
    }
}
