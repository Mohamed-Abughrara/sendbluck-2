<?php
declare(strict_types=1);

namespace MailDesk\Application\Contact;

use MailDesk\Application\Port\{AuditLoggerInterface, ClockInterface};
use MailDesk\Domain\Contact\ContactRepositoryInterface;
use MailDesk\Domain\Exception\ValidationFailed;

final class AddContact
{
    public function __construct(
        private ContactRepositoryInterface $contacts,
        private AuditLoggerInterface $audit,
        private ClockInterface $clock,
    ) {
    }

    /** @throws ValidationFailed */
    public function execute(ContactInput $in, int $userId): int
    {
        $contact = ContactFactory::build(null, $in);
        if ($this->contacts->emailExists($contact->email)) {
            throw new ValidationFailed(['email' => 'A contact with this email already exists.']);
        }
        $id = $this->contacts->add($contact, $userId, $this->clock->now()->format('Y-m-d H:i:s'));
        $this->audit->log($userId, 'contact.created', ['contact_id' => $id]);
        return $id;
    }
}
