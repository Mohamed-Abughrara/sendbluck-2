<?php
declare(strict_types=1);

namespace MailDesk\Application\Configuration;

use MailDesk\Domain\Configuration\Encryption;

/** Keyed hash of the connection-relevant values (host/port/encryption/username/password). */
final class ConfigurationFingerprint
{
    public static function of(
        string $key,
        string $host,
        int $port,
        Encryption $encryption,
        string $username,
        string $password,
    ): string {
        return hash_hmac(
            'sha256',
            implode("\0", [strtolower($host), $port, $encryption->value, $username, $password]),
            $key,
        );
    }
}
