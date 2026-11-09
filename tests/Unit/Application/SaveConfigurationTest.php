<?php
declare(strict_types=1);

namespace MailDesk\Tests\Unit\Application;

use MailDesk\Application\Configuration\{ConfigurationFingerprint, ConfigurationInput, SaveConfiguration};
use MailDesk\Application\Port\{AuditLoggerInterface, ClockInterface, EncryptorInterface};
use MailDesk\Domain\Configuration\{Encryption, MailConfiguration, MailConfigurationRepositoryInterface};
use MailDesk\Domain\Exception\ValidationFailed;
use MailDesk\Domain\Shared\EmailAddress;
use PHPUnit\Framework\TestCase;

final class SaveConfigurationTest extends TestCase
{
    private const KEY = 'test-key';

    private function encryptor(): EncryptorInterface
    {
        return new class implements EncryptorInterface {
            public function encrypt(string $plain): string
            {
                return 'enc(' . $plain . ')';
            }

            public function decrypt(string $cipher): string
            {
                return substr($cipher, 4, -1);
            }
        };
    }

    private function clock(): ClockInterface
    {
        return new class implements ClockInterface {
            public function now(): \DateTimeImmutable
            {
                return new \DateTimeImmutable('2026-11-09 10:00:00', new \DateTimeZone('UTC'));
            }
        };
    }

    /** @return array{SaveConfiguration, object, object} */
    private function build(?MailConfiguration $stored): array
    {
        $repo = new class ($stored) implements MailConfigurationRepositoryInterface {
            public ?MailConfiguration $saved = null;

            public function __construct(private ?MailConfiguration $stored)
            {
            }

            public function getActive(): ?MailConfiguration
            {
                return $this->stored;
            }

            public function save(MailConfiguration $c, int $userId, string $now): void
            {
                $this->saved = $c;
            }

            public function markTested(bool $verified, string $now): void
            {
            }
        };
        $audit = new class implements AuditLoggerInterface {
            /** @var list<array{string,array<string,mixed>}> */
            public array $entries = [];

            public function log(?int $userId, string $action, array $context = []): void
            {
                $this->entries[] = [$action, $context];
            }
        };
        return [new SaveConfiguration($repo, $this->encryptor(), $audit, $this->clock(), self::KEY), $repo, $audit];
    }

    private function input(?string $password, int $port = 587): ConfigurationInput
    {
        return new ConfigurationInput('Admin', 'admin@example.org', 'smtp.example.org', $port, 'tls', 'user', $password);
    }

    private function fp(string $password, int $port = 587): string
    {
        return ConfigurationFingerprint::of(self::KEY, 'smtp.example.org', $port, Encryption::Tls, 'user', $password);
    }

    public function test_it_encrypts_the_password_and_is_unverified_without_a_matching_test(): void
    {
        [$uc, $repo, $audit] = $this->build(null);
        $c = $uc->execute($this->input('s3cret'), 1);

        self::assertSame('enc(s3cret)', $repo->saved->encryptedPassword);
        self::assertFalse($c->isVerified());
        self::assertStringNotContainsString('s3cret', json_encode($audit->entries));
    }

    public function test_it_is_verified_only_with_a_fresh_matching_test_fingerprint(): void
    {
        [$uc] = $this->build(null);
        $now = (new \DateTimeImmutable('2026-11-09 10:00:00', new \DateTimeZone('UTC')))->getTimestamp();

        self::assertTrue($uc->execute($this->input('pw'), 1, $this->fp('pw'), $now - 60)->isVerified());
        self::assertFalse($uc->execute($this->input('pw'), 1, $this->fp('other'), $now - 60)->isVerified(), 'different password');
        self::assertFalse($uc->execute($this->input('pw', 465), 1, $this->fp('pw', 587), $now - 60)->isVerified(), 'different port');
        self::assertFalse($uc->execute($this->input('pw'), 1, $this->fp('pw'), $now - 3600)->isVerified(), 'stale test');
    }

    public function test_empty_password_keeps_existing_ciphertext_and_verification(): void
    {
        $stored = new MailConfiguration(1, 'Admin', new EmailAddress('admin@example.org'), 'smtp.example.org', 587, Encryption::Tls, 'user', 'enc(old)', true, '2026-11-01 00:00:00');
        [$uc, $repo] = $this->build($stored);

        $c = $uc->execute($this->input(null), 1);

        self::assertSame('enc(old)', $repo->saved->encryptedPassword);
        self::assertTrue($c->isVerified());
    }

    public function test_changing_a_connection_field_resets_verification(): void
    {
        $stored = new MailConfiguration(1, 'Admin', new EmailAddress('admin@example.org'), 'smtp.example.org', 587, Encryption::Tls, 'user', 'enc(old)', true, '2026-11-01 00:00:00');
        [$uc] = $this->build($stored);

        self::assertFalse($uc->execute($this->input(null, 2525), 1)->isVerified());
    }

    public function test_password_is_required_on_first_save(): void
    {
        [$uc] = $this->build(null);
        try {
            $uc->execute($this->input(null), 1);
            self::fail('Expected ValidationFailed');
        } catch (ValidationFailed $e) {
            self::assertArrayHasKey('smtp_password', $e->errors());
        }
    }

    public function test_audit_records_changed_field_names_only(): void
    {
        $stored = new MailConfiguration(1, 'Admin', new EmailAddress('admin@example.org'), 'smtp.example.org', 587, Encryption::Tls, 'user', 'enc(old)', true);
        [$uc, , $audit] = $this->build($stored);

        $uc->execute($this->input('new-secret', 2525), 1);

        [$action, $ctx] = $audit->entries[0];
        self::assertSame('config.updated', $action);
        self::assertSame(['smtp_port', 'smtp_password'], $ctx['changed']);
        self::assertStringNotContainsString('new-secret', json_encode($audit->entries));
    }
}
