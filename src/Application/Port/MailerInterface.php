<?php
declare(strict_types=1);

namespace MailDesk\Application\Port;

use MailDesk\Domain\Configuration\MailConfiguration;

interface MailerInterface
{
    public function send(MailConfiguration $config, string $plainPassword, OutgoingEmail $email): SendResult;

    public function testConnection(MailConfiguration $config, string $plainPassword): SendResult;
}
