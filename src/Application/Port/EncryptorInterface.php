<?php
declare(strict_types=1);

namespace MailDesk\Application\Port;

interface EncryptorInterface
{
    public function encrypt(string $plain): string;

    /** @throws \RuntimeException if the cipher text is invalid or tampered with */
    public function decrypt(string $cipher): string;
}
