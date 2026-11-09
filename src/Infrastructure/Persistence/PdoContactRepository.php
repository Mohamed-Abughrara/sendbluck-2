<?php
declare(strict_types=1);

namespace MailDesk\Infrastructure\Persistence;

use MailDesk\Domain\Contact\{Contact, ContactRepositoryInterface};
use MailDesk\Domain\Exception\{DuplicateContact, NotFound};
use MailDesk\Domain\Shared\EmailAddress;
use PDO;
use PDOException;

final class PdoContactRepository implements ContactRepositoryInterface
{
    private const SORT_COLUMNS = ['name' => 'name', 'email' => 'email', 'created' => 'created_at'];
    private const ESC = " ESCAPE '!'";

    public function __construct(private PDO $pdo)
    {
    }

    public function add(Contact $contact, int $userId, string $now): int
    {
        try {
            $st = $this->pdo->prepare(
                'INSERT INTO contacts (name, email, created_by, created_at, updated_at) VALUES (?, ?, ?, ?, ?)'
            );
            $st->execute([$contact->name, (string) $contact->email, $userId, $now, $now]);
        } catch (PDOException $e) {
            if ((string) $e->getCode() === '23000') {
                throw new DuplicateContact();
            }
            throw $e;
        }
        return (int) $this->pdo->lastInsertId();
    }

    public function update(Contact $contact, string $now): void
    {
        try {
            $st = $this->pdo->prepare('UPDATE contacts SET name = ?, email = ?, updated_at = ? WHERE id = ? AND deleted_at IS NULL');
            $st->execute([$contact->name, (string) $contact->email, $now, $contact->id]);
        } catch (PDOException $e) {
            if ((string) $e->getCode() === '23000') {
                throw new DuplicateContact();
            }
            throw $e;
        }
    }

    public function delete(int $id, string $now): bool
    {
        $st = $this->pdo->prepare('UPDATE contacts SET deleted_at = ?, updated_at = ? WHERE id = ? AND deleted_at IS NULL');
        $st->execute([$now, $now, $id]);
        return $st->rowCount() > 0;
    }

    public function findById(int $id): ?Contact
    {
        $st = $this->pdo->prepare('SELECT * FROM contacts WHERE id = ? AND deleted_at IS NULL');
        $st->execute([$id]);
        $row = $st->fetch();
        return $row ? $this->hydrate($row) : null;
    }

    public function findByIds(array $ids): array
    {
        $ids = array_values(array_unique(array_map('intval', $ids)));
        if ($ids === []) {
            return [];
        }
        $in = implode(',', array_fill(0, count($ids), '?'));
        $st = $this->pdo->prepare("SELECT * FROM contacts WHERE deleted_at IS NULL AND id IN ($in)");
        $st->execute($ids);
        $byId = [];
        foreach ($st->fetchAll() as $row) {
            $byId[(int) $row['id']] = $this->hydrate($row);
        }
        $out = [];
        foreach ($ids as $id) {
            if (!isset($byId[$id])) {
                throw new NotFound('Contact');
            }
            $out[] = $byId[$id];
        }
        return $out;
    }

    public function emailExists(EmailAddress $email, ?int $exceptId = null): bool
    {
        $sql = 'SELECT 1 FROM contacts WHERE email = ? AND deleted_at IS NULL';
        $params = [(string) $email];
        if ($exceptId !== null) {
            $sql .= ' AND id <> ?';
            $params[] = $exceptId;
        }
        $st = $this->pdo->prepare($sql . ' LIMIT 1');
        $st->execute($params);
        return $st->fetchColumn() !== false;
    }

    public function search(string $q, string $sort, string $dir, int $page, int $perPage): array
    {
        $col = self::SORT_COLUMNS[$sort] ?? 'name';
        $direction = strtolower($dir) === 'desc' ? 'DESC' : 'ASC';
        $where = 'deleted_at IS NULL';
        $params = [];
        if ($q !== '') {
            $like = '%' . $this->escapeLike($q) . '%';
            $where .= ' AND (name LIKE ?' . self::ESC . ' OR email LIKE ?' . self::ESC . ')';
            $params = [$like, $like];
        }

        $st = $this->pdo->prepare("SELECT COUNT(*) FROM contacts WHERE $where");
        $st->execute($params);
        $total = (int) $st->fetchColumn();

        $st = $this->pdo->prepare("SELECT * FROM contacts WHERE $where ORDER BY $col $direction, id ASC LIMIT ? OFFSET ?");
        $i = 1;
        foreach ($params as $p) {
            $st->bindValue($i++, $p);
        }
        $st->bindValue($i++, $perPage, PDO::PARAM_INT);
        $st->bindValue($i, ($page - 1) * $perPage, PDO::PARAM_INT);
        $st->execute();

        return ['items' => array_map($this->hydrate(...), $st->fetchAll()), 'total' => $total];
    }

    public function suggest(string $q, int $limit = 10): array
    {
        $esc = $this->escapeLike($q);
        $e = self::ESC;
        $st = $this->pdo->prepare(
            "SELECT * FROM contacts WHERE deleted_at IS NULL
             AND (name LIKE ?$e OR email LIKE ?$e)
             ORDER BY CASE WHEN (name LIKE ?$e OR email LIKE ?$e) THEN 0 ELSE 1 END, name ASC
             LIMIT ?"
        );
        $contains = '%' . $esc . '%';
        $prefix = $esc . '%';
        $st->bindValue(1, $contains);
        $st->bindValue(2, $contains);
        $st->bindValue(3, $prefix);
        $st->bindValue(4, $prefix);
        $st->bindValue(5, $limit, PDO::PARAM_INT);
        $st->execute();
        return array_map($this->hydrate(...), $st->fetchAll());
    }

    public function count(): int
    {
        return (int) $this->pdo->query('SELECT COUNT(*) FROM contacts WHERE deleted_at IS NULL')->fetchColumn();
    }

    /** @param array<string,mixed> $row */
    private function hydrate(array $row): Contact
    {
        return new Contact((int) $row['id'], (string) $row['name'], new EmailAddress((string) $row['email']), (string) $row['created_at']);
    }

    private function escapeLike(string $s): string
    {
        return str_replace(['!', '%', '_'], ['!!', '!%', '!_'], $s);
    }
}
