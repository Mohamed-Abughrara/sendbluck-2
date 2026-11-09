<?php
declare(strict_types=1);

namespace MailDesk\Application\Mail;

final readonly class SendMessageOutput
{
    public function __construct(public int $messageId, public bool $sent, public ?string $error)
    {
    }
}
