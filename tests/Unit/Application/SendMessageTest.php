<?php
declare(strict_types=1);

namespace MailDesk\Tests\Unit\Application;

use MailDesk\Application\Mail\{SendMessage, SendMessageInput};
use MailDesk\Application\Port\{AuditLoggerInterface, ClockInterface, EncryptorInterface, MailerInterface, OutgoingEmail, SendResult};
use MailDesk\Domain\Configuration\{Encryption, MailConfiguration, MailConfigurationRepositoryInterface};
use MailDesk\Domain\Contact\{Contact, ContactRepositoryInterface};
use MailDesk\Domain\Exception\{ConfigurationNotVerified, TooManyRecipients, ValidationFailed};
use MailDesk\Domain\Message\{Message, MessageRepositoryInterface, MessageStatus};
use MailDesk\Domain\Shared\EmailAddress;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

final class SendMessageTest extends TestCase
{
    private MailConfigurationRepositoryInterface&MockObject $configs;
    private ContactRepositoryInterface&MockObject $contacts;
    private MessageRepositoryInterface&MockObject $messages;
    private MailerInterface&MockObject $mailer;
    private EncryptorInterface&MockObject $encryptor;
    private AuditLoggerInterface&MockObject $audit;
    private ClockInterface $clock;

    protected function setUp(): void
    {
        $this->configs = $this->createMock(MailConfigurationRepositoryInterface::class);
        $this->contacts = $this->createMock(ContactRepositoryInterface::class);
        $this->messages = $this->createMock(MessageRepositoryInterface::class);
        $this->mailer = $this->createMock(MailerInterface::class);
        $this->encryptor = $this->createMock(EncryptorInterface::class);
        $this->audit = $this->createMock(AuditLoggerInterface::class);
        $this->clock = new class implements ClockInterface {
            public function now(): \DateTimeImmutable
            {
                return new \DateTimeImmutable('2026-11-09 10:00:00', new \DateTimeZone('UTC'));
            }
        };
    }

    private function useCase(int $max = 50): SendMessage
    {
        return new SendMessage($this->configs, $this->contacts, $this->messages, $this->mailer, $this->encryptor, $this->audit, $this->clock, $max);
    }

    private function config(bool $verified): MailConfiguration
    {
        return new MailConfiguration(1, 'Admin', new EmailAddress('admin@example.org'), 'smtp.example.org', 587, Encryption::Tls, 'user', 'CIPHER', $verified);
    }

    private function contact(int $id): Contact
    {
        return new Contact($id, "Contact $id", new EmailAddress("c$id@example.com"));
    }

    public function test_it_never_calls_the_mailer_when_config_is_missing(): void
    {
        $this->configs->method('getActive')->willReturn(null);
        $this->mailer->expects($this->never())->method('send');

        $this->expectException(ConfigurationNotVerified::class);
        $this->useCase()->execute(new SendMessageInput(1, [10], [], 'Hello', '<p>Hi</p>'));
    }

    public function test_it_never_calls_the_mailer_when_config_is_unverified(): void
    {
        $this->configs->method('getActive')->willReturn($this->config(false));
        $this->mailer->expects($this->never())->method('send');

        $this->expectException(ConfigurationNotVerified::class);
        $this->useCase()->execute(new SendMessageInput(1, [10], [], 'Hello', '<p>Hi</p>'));
    }

    public function test_it_rejects_empty_recipients_subject_and_body(): void
    {
        $this->configs->method('getActive')->willReturn($this->config(true));
        $this->mailer->expects($this->never())->method('send');

        try {
            $this->useCase()->execute(new SendMessageInput(1, [], [], '   ', '<p> </p>'));
            self::fail('Expected ValidationFailed');
        } catch (ValidationFailed $e) {
            self::assertSame(['subject', 'body', 'to'], array_keys($e->errors()));
        }
    }

    public function test_it_rejects_more_than_the_maximum_recipients(): void
    {
        $this->configs->method('getActive')->willReturn($this->config(true));
        $this->mailer->expects($this->never())->method('send');

        $this->expectException(TooManyRecipients::class);
        $this->useCase(2)->execute(new SendMessageInput(1, [1, 2], [3], 'S', '<p>b</p>'));
    }

    public function test_successful_send_is_saved_audited_and_subject_is_single_line(): void
    {
        $this->configs->method('getActive')->willReturn($this->config(true));
        $this->contacts->method('findByIds')->willReturn([$this->contact(10)]);
        $this->encryptor->expects($this->once())->method('decrypt')->with('CIPHER')->willReturn('plain-pw');

        $this->mailer->expects($this->once())->method('send')
            ->with($this->anything(), 'plain-pw', $this->callback(
                static fn (OutgoingEmail $e): bool => $e->subject === 'Line1 Line2' && !str_contains($e->bodyHtml, '<script'),
            ))
            ->willReturn(SendResult::ok());

        $saved = null;
        $this->messages->expects($this->once())->method('save')->willReturnCallback(function (Message $m) use (&$saved): int {
            $saved = $m;
            return 77;
        });
        $this->audit->expects($this->once())->method('log')->with(1, 'message.sent', ['message_id' => 77, 'recipients' => 1]);

        $out = $this->useCase()->execute(new SendMessageInput(1, [10], [], "Line1\r\nLine2", '<p>Hi</p><script>x()</script>'));

        self::assertTrue($out->sent);
        self::assertSame(77, $out->messageId);
        self::assertSame(MessageStatus::Sent, $saved->status);
    }

    public function test_failed_send_is_saved_as_failed_with_sanitised_error(): void
    {
        $this->configs->method('getActive')->willReturn($this->config(true));
        $this->contacts->method('findByIds')->willReturn([$this->contact(10)]);
        $this->encryptor->method('decrypt')->willReturn('pw');
        $this->mailer->method('send')->willReturn(SendResult::failed('Could not connect to host.'));

        $saved = null;
        $this->messages->method('save')->willReturnCallback(function (Message $m) use (&$saved): int {
            $saved = $m;
            return 5;
        });
        $this->audit->expects($this->once())->method('log')->with(1, 'message.failed', $this->anything());

        $out = $this->useCase()->execute(new SendMessageInput(1, [10], [], 'S', '<p>b</p>'));

        self::assertFalse($out->sent);
        self::assertSame(MessageStatus::Failed, $saved->status);
        self::assertSame('Could not connect to host.', $saved->error);
    }
}
