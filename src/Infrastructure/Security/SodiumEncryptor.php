<?php
declare(strict_types=1);

namespace MailDesk\Infrastructure\Security;

use MailDesk\Application\Port\EncryptorInterface;

final class SodiumEncryptor implements EncryptorInterface
{
    private string $key;

    /** @param string $appKey "base64:..." or raw base64 of 32 bytes */
    public function __construct(string $appKey)
    {
        $this->key = self::parseKey($appKey);
    }

    public static function parseKey(string $appKey): string
    {
        $b64 = str_starts_with($appKey, 'base64:') ? substr($appKey, 7) : $appKey;
        $key = base64_decode($b64, true);
        if ($key === false || strlen($key) !== SODIUM_CRYPTO_SECRETBOX_KEYBYTES) {
            throw new \RuntimeException('APP_KEY must be base64 of exactly 32 random bytes. Run: php bin/console key:generate');
        }
        return $key;
    }

    public function encrypt(string $plain): string
    {
        $nonce = random_bytes(SODIUM_CRYPTO_SECRETBOX_NONCEBYTES);
        $cipher = sodium_crypto_secretbox($plain, $nonce, $this->key);
        return base64_encode($nonce . $cipher);
    }

    public function decrypt(string $cipher): string
    {
        $raw = base64_decode($cipher, true);
        if ($raw === false || strlen($raw) < SODIUM_CRYPTO_SECRETBOX_NONCEBYTES + SODIUM_CRYPTO_SECRETBOX_MACBYTES) {
            throw new \RuntimeException('Stored secret is invalid.');
        }
        $nonce = substr($raw, 0, SODIUM_CRYPTO_SECRETBOX_NONCEBYTES);
        $plain = sodium_crypto_secretbox_open(substr($raw, SODIUM_CRYPTO_SECRETBOX_NONCEBYTES), $nonce, $this->key);
        if ($plain === false) {
            throw new \RuntimeException('Stored secret could not be decrypted (wrong APP_KEY or tampered data).');
        }
        return $plain;
    }

    public function __destruct()
    {
        sodium_memzero($this->key);
    }
}
