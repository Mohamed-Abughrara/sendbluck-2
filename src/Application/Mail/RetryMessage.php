<?php
declare(strict_types=1);

namespace MailDesk\Application\Mail;

use MailDesk\Domain\Exception\{NotFound, ValidationFailed};
use MailDesk\Domain\Message\{MessageRepositoryInterface, MessageStatus};

final class RetryMessage
{
    public function __construct(
        private MessageRepositoryInterface $messages,
        private SendMessage $sender,
    ) {
    }

    public function execute(int $userId, int $messageId): SendMessageOutput
    {
        $orig = $this->messages->findById($messageId);
        if ($orig === null) {
            throw new NotFound('Message');
        }
        if ($orig->status !== MessageStatus::Failed || $orig->retried) {
            throw new ValidationFailed(['message' => 'Only failed messages that were not retried can be retried.']);
        }

        $out = $this->sender->dispatch($userId, $orig->to, $orig->cc, $orig->subject, $orig->bodyHtml);
        $this->messages->markRetried($messageId);

        return $out;
    }
}
