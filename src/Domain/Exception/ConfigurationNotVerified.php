<?php
declare(strict_types=1);

namespace MailDesk\Domain\Exception;

final class ConfigurationNotVerified extends DomainException
{
    public function __construct()
    {
        parent::__construct('Sending is disabled. Configure and test your mail account first.');
    }
}
