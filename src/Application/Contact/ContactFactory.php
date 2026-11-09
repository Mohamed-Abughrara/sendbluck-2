<?php
declare(strict_types=1);

namespace MailDesk\Application\Contact;

use MailDesk\Domain\Contact\Contact;
use MailDesk\Domain\Exception\{InvalidEmailAddress, ValidationFailed};
use MailDesk\Domain\Shared\EmailAddress;

final class ContactFactory
{
    public static function build(?int $id, ContactInput $in): Contact
    {
        $errors = [];
        $email = null;
        try {
            $email = new EmailAddress($in->email);
        } catch (InvalidEmailAddress $e) {
            $errors['email'] = $e->getMessage();
        }
        $name = trim($in->name);
        if ($name === '' || mb_strlen($name) > 100) {
            $errors['name'] = 'Name is required (max 100 characters).';
        }
        if ($errors !== []) {
            throw new ValidationFailed($errors);
        }
        return new Contact($id, $name, $email);
    }
}
