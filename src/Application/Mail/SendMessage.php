<?php
declare(strict_types=1);

namespace MailDesk\Application\Mail;

use MailDesk\Application\Port\{AuditLoggerInterface, ClockInterface, EncryptorInterface, MailerInterface, OutgoingEmail};
use MailDesk\Domain\Configuration\MailConfigurationRepositoryInterface;
use MailDesk\Domain\Contact\{Contact, ContactRepositoryInterface};
use MailDesk\Domain\Exception\{ConfigurationNotVerified, NotFound, TooManyRecipients, ValidationFailed};
use MailDesk\Domain\Message\{Message, MessageRepositoryInterface, MessageStatus};

final class SendMessage
{
    public function __construct(
        private MailConfigurationRepositoryInterface $configs,
        private ContactRepositoryInterface $contacts,
        private MessageRepositoryInterface $messages,
        private MailerInterface $mailer,
        private EncryptorInterface $encryptor,
        private AuditLoggerInterface $audit,
        private ClockInterface $clock,
        private int $maxRecipients = 50,
    ) {
    }

    public function execute(SendMessageInput $in): SendMessageOutput
    {
        $this->assertVerified();

        $subject = self::cleanSubject($in->subject);
        $errors = [];
        if ($subject === '' || mb_strlen($subject) > 200) {
            $errors['subject'] = 'Subject is required (max 200 characters).';
        }
        if (trim(strip_tags($in->bodyHtml)) === '' || mb_strlen($in->bodyHtml) > 100000) {
            $errors['body'] = 'Message body is required (max 100 000 characters).';
        }
        $toIds = array_values(array_unique($in->toContactIds));
        $ccIds = array_values(array_diff(array_unique($in->ccContactIds), $toIds));
        if ($toIds === []) {
            $errors['to'] = 'Choose at least one recipient.';
        }
        if ($errors !== []) {
            throw new ValidationFailed($errors);
        }
        if (count($toIds) + count($ccIds) > $this->maxRecipients) {
            throw new TooManyRecipients($this->maxRecipients);
        }

        try {
            $to = $this->contacts->findByIds($toIds);
            $cc = $ccIds === [] ? [] : $this->contacts->findByIds($ccIds);
        } catch (NotFound) {
            throw new ValidationFailed(['to' => 'One or more recipients no longer exist.']);
        }

        $map = static fn (Contact $c): array => ['name' => $c->name, 'email' => (string) $c->email];

        return $this->dispatch($in->userId, array_map($map, $to), array_map($map, $cc), $subject, $in->bodyHtml);
    }

    /**
     * Deliver an already-validated message snapshot and persist the outcome.
     *
     * @param list<array{name:string,email:string}> $to
     * @param list<array{name:string,email:string}> $cc
     */
    public function dispatch(int $userId, array $to, array $cc, string $subject, string $bodyHtml): SendMessageOutput
    {
        $config = $this->assertVerified();

        $clean = HtmlSanitizer::clean($bodyHtml);
        $email = new OutgoingEmail($to, $cc, self::cleanSubject($subject), $clean, HtmlSanitizer::toText($clean));

        $password = $this->encryptor->decrypt($config->encryptedPassword);
        $result = $this->mailer->send($config, $password, $email);
        $password = '';
        unset($password);

        $message = new Message(
            id: null,
            userId: $userId,
            to: $email->to,
            cc: $email->cc,
            subject: $email->subject,
            bodyHtml: $clean,
            status: $result->success ? MessageStatus::Sent : MessageStatus::Failed,
            error: $result->error,
            sentAt: $this->clock->now(),
        );
        $id = $this->messages->save($message);

        $this->audit->log(
            $userId,
            $result->success ? 'message.sent' : 'message.failed',
            ['message_id' => $id, 'recipients' => $message->recipientCount()],
        );

        return new SendMessageOutput($id, $result->success, $result->error);
    }

    private function assertVerified(): \MailDesk\Domain\Configuration\MailConfiguration
    {
        $config = $this->configs->getActive();
        if ($config === null || !$config->isVerified()) {
            throw new ConfigurationNotVerified();
        }
        return $config;
    }

    private static function cleanSubject(string $s): string
    {
        return trim(preg_replace('/[\r\n\t]+/', ' ', $s) ?? '');
    }
}
