<?php
declare(strict_types=1);

namespace MailDesk\Domain\Configuration;

enum Encryption: string
{
    case Tls = 'tls';
    case Ssl = 'ssl';
    case None = 'none';
}
