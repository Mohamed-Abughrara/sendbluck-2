<?php
declare(strict_types=1);

namespace MailDesk\Domain\Contact;

use MailDesk\Domain\Shared\EmailAddress;

interface ContactRepositoryInterface
{
    public function add(Contact $contact, int $userId, string $now): int;

    public function update(Contact $contact, string $now): void;

    /** Soft delete. Returns false if it did not exist. */
    public function delete(int $id, string $now): bool;

    public function findById(int $id): ?Contact;

    /**
     * @param list<int> $ids
     * @return list<Contact>
     * @throws \MailDesk\Domain\Exception\NotFound if any id is missing
     */
    public function findByIds(array $ids): array;

    public function emailExists(EmailAddress $email, ?int $exceptId = null): bool;

    /** @return array{items: list<Contact>, total: int} */
    public function search(string $q, string $sort, string $dir, int $page, int $perPage): array;

    /** @return list<Contact> */
    public function suggest(string $q, int $limit = 10): array;

    public function count(): int;
}
