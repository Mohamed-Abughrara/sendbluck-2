<?php
declare(strict_types=1);

namespace MailDesk\Application\Contact;

use MailDesk\Domain\Contact\{Contact, ContactRepositoryInterface};

final class ListContacts
{
    public const SORTS = ['name', 'email', 'created'];

    public function __construct(private ContactRepositoryInterface $contacts)
    {
    }

    /** @return array{items: list<Contact>, total: int, page: int, pages: int} */
    public function execute(string $q, string $sort, string $dir, int $page, int $perPage = 20): array
    {
        $sort = in_array($sort, self::SORTS, true) ? $sort : 'name';
        $dir = strtolower($dir) === 'desc' ? 'desc' : 'asc';
        $page = max(1, $page);
        $result = $this->contacts->search(trim($q), $sort, $dir, $page, $perPage);
        $pages = max(1, (int) ceil($result['total'] / $perPage));
        return $result + ['page' => $page, 'pages' => $pages];
    }

    /** @return list<Contact> */
    public function suggest(string $q, int $limit = 10): array
    {
        return $this->contacts->suggest(trim($q), $limit);
    }
}
