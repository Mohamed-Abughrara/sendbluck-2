<?php
declare(strict_types=1);

namespace MailDesk\Application\Port;

interface ClockInterface
{
    public function now(): \DateTimeImmutable;
}
