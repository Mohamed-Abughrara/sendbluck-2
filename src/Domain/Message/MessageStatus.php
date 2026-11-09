<?php
declare(strict_types=1);

namespace MailDesk\Domain\Message;

enum MessageStatus: string
{
    case Sent = 'sent';
    case Failed = 'failed';
}
