<?php
declare(strict_types=1);

namespace MailDesk\Tests\Unit\Infrastructure;

use MailDesk\Application\Mail\HtmlSanitizer;
use MailDesk\Infrastructure\Logging\FileErrorLogger;
use MailDesk\Infrastructure\Mail\PHPMailerAdapter;
use MailDesk\Infrastructure\Security\SodiumEncryptor;
use PHPUnit\Framework\TestCase;

final class SecurityTest extends TestCase
{
    private function key(): string
    {
        return 'base64:' . base64_encode(random_bytes(32));
    }

    public function test_encryptor_round_trips_with_a_fresh_nonce_each_time(): void
    {
        $e = new SodiumEncryptor($this->key());
        $a = $e->encrypt('smtp-secret');
        $b = $e->encrypt('smtp-secret');

        self::assertNotSame($a, $b);
        self::assertStringNotContainsString('smtp-secret', $a);
        self::assertSame('smtp-secret', $e->decrypt($a));
    }

    public function test_tampered_ciphertext_throws(): void
    {
        $e = new SodiumEncryptor($this->key());
        $raw = base64_decode($e->encrypt('x'), true);
        $raw[strlen($raw) - 1] = $raw[strlen($raw) - 1] ^ "\x01";

        $this->expectException(\RuntimeException::class);
        $e->decrypt(base64_encode($raw));
    }

    public function test_wrong_key_throws(): void
    {
        $cipher = (new SodiumEncryptor($this->key()))->encrypt('x');
        $this->expectException(\RuntimeException::class);
        (new SodiumEncryptor($this->key()))->decrypt($cipher);
    }

    public function test_key_must_be_32_bytes(): void
    {
        $this->expectException(\RuntimeException::class);
        new SodiumEncryptor('base64:' . base64_encode('short'));
    }

    public function test_sanitise_maps_raw_errors_to_safe_text_without_leaking_input(): void
    {
        $raw = 'SMTP Error: Could not authenticate. user=bob password=hunter2';
        $msg = PHPMailerAdapter::sanitise($raw);

        self::assertSame('Authentication failed (check username/password).', $msg);
        self::assertStringNotContainsString('hunter2', $msg);
        self::assertSame('Could not connect to host.', PHPMailerAdapter::sanitise('SMTP connect() failed.'));
        self::assertSame('The mail server rejected the message.', PHPMailerAdapter::sanitise('550 spam detected'));
    }

    public function test_html_sanitiser_removes_scripts_handlers_and_unsafe_links(): void
    {
        $html = '<p onclick="x()">Hi <b>b</b><script>alert(1)</script><img src=x onerror=alert(1)>'
            . '<a href="javascript:alert(1)">j</a><a href=" JaVaScRiPt:alert(1)">j2</a><a href="https://ok.test/x">ok</a></p>';
        $out = HtmlSanitizer::clean($html);

        self::assertStringNotContainsString('script', strtolower($out));
        self::assertStringNotContainsString('onclick', $out);
        self::assertStringNotContainsString('onerror', $out);
        self::assertStringNotContainsString('javascript', strtolower($out));
        self::assertStringContainsString('<b>b</b>', $out);
        self::assertStringContainsString('href="https://ok.test/x"', $out);
        self::assertStringContainsString('rel="noopener noreferrer"', $out);
    }

    public function test_logger_redacts_secrets(): void
    {
        $r = FileErrorLogger::redact(['password' => 'p', 'nested' => ['smtp_password' => 'q', 'ok' => 'fine'], 'msg' => 'password=hunter2 failed']);

        self::assertSame('[redacted]', $r['password']);
        self::assertSame('[redacted]', $r['nested']['smtp_password']);
        self::assertSame('fine', $r['nested']['ok']);
        self::assertStringNotContainsString('hunter2', $r['msg']);
    }
}
