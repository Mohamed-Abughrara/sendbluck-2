<?php
declare(strict_types=1);

namespace MailDesk\Tests\Integration;

use MailDesk\Domain\Contact\Contact;
use MailDesk\Domain\Exception\{DuplicateContact, NotFound};
use MailDesk\Domain\Message\{Message, MessageStatus};
use MailDesk\Domain\Shared\EmailAddress;
use MailDesk\Infrastructure\Persistence\{Migrator, PdoContactRepository, PdoMessageRepository};
use MailDesk\Infrastructure\Security\PdoRateLimiter;
use PDO;
use PHPUnit\Framework\TestCase;

final class SqliteRepositoriesTest extends TestCase
{
    private PDO $pdo;

    protected function setUp(): void
    {
        $this->pdo = new PDO('sqlite::memory:', null, null, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ]);
        $this->pdo->exec('PRAGMA foreign_keys = ON');
        (new Migrator($this->pdo, dirname(__DIR__, 2) . '/database/migrations'))->run();
        $this->pdo->exec("INSERT INTO users (name,email,password_hash,created_at,updated_at) VALUES ('A','a@x.com','h','2026-01-01','2026-01-01')");
    }

    private function contact(string $email, string $name = 'N'): Contact
    {
        return new Contact(null, $name, new EmailAddress($email));
    }

    public function test_duplicate_active_email_fails_but_readding_after_soft_delete_works(): void
    {
        $repo = new PdoContactRepository($this->pdo);
        $id = $repo->add($this->contact('ali@example.com'), 1, '2026-11-09 10:00:00');

        try {
            $repo->add($this->contact('ali@example.com'), 1, '2026-11-09 10:00:00');
            self::fail('Expected DuplicateContact');
        } catch (DuplicateContact) {
            self::assertTrue(true);
        }

        self::assertTrue($repo->delete($id, '2026-11-09 11:00:00'));
        self::assertFalse($repo->delete($id, '2026-11-09 11:00:00'), 'already deleted');
        self::assertNull($repo->findById($id));
        self::assertGreaterThan($id, $repo->add($this->contact('ali@example.com'), 1, '2026-11-09 12:00:00'));
    }

    public function test_search_is_case_insensitive_escapes_wildcards_and_paginates(): void
    {
        $repo = new PdoContactRepository($this->pdo);
        $repo->add($this->contact('ali@example.com', 'Ali Ahmed'), 1, '2026-11-09');
        $repo->add($this->contact('sara@example.com', 'Sara'), 1, '2026-11-09');
        $repo->add($this->contact('x@example.com', '100% Real'), 1, '2026-11-09');

        self::assertSame(1, $repo->search('ALI', 'name', 'asc', 1, 20)['total']);
        self::assertSame(1, $repo->search('%', 'name', 'asc', 1, 20)['total'], 'literal percent only');
        self::assertCount(2, $repo->search('', 'name', 'asc', 1, 2)['items']);
        self::assertSame('Sara', $repo->search('', 'name', 'desc', 1, 1)['items'][0]->name);
        self::assertSame('Ali Ahmed', $repo->suggest('al', 10)[0]->name);
    }

    public function test_find_by_ids_throws_if_any_is_missing_or_deleted(): void
    {
        $repo = new PdoContactRepository($this->pdo);
        $a = $repo->add($this->contact('a@example.com'), 1, '2026-11-09');
        $b = $repo->add($this->contact('b@example.com'), 1, '2026-11-09');
        $repo->delete($b, '2026-11-09');

        self::assertCount(1, $repo->findByIds([$a]));
        $this->expectException(NotFound::class);
        $repo->findByIds([$a, $b]);
    }

    public function test_messages_round_trip_recipients_and_list_newest_first(): void
    {
        $repo = new PdoMessageRepository($this->pdo);
        $mk = fn (string $subject, string $at, MessageStatus $s): Message => new Message(
            null, 1, [['name' => 'Ali', 'email' => 'ali@example.com']], [['name' => 'Zoë', 'email' => 'z@example.com']],
            $subject, '<p>b</p>', $s, $s === MessageStatus::Failed ? 'boom' : null, new \DateTimeImmutable($at, new \DateTimeZone('UTC')),
        );
        $repo->save($mk('old', '2026-11-01 10:00:00', MessageStatus::Sent));
        $newId = $repo->save($mk('new', '2026-11-02 10:00:00', MessageStatus::Sent));
        $failedId = $repo->save($mk('bad', '2026-11-03 10:00:00', MessageStatus::Failed));

        $sent = $repo->list(MessageStatus::Sent, '', 1, 25);
        self::assertSame(['new', 'old'], array_map(fn (Message $m): string => $m->subject, $sent));
        self::assertSame('Zoë', $sent[0]->cc[0]['name']);
        self::assertSame(2, $repo->countByStatus(MessageStatus::Sent));
        self::assertSame(1, $repo->countByStatus(null, 'bad'));
        self::assertSame('2026-11-02 10:00:00', $repo->lastSentAt());

        $repo->markRetried($failedId);
        self::assertTrue($repo->findById($failedId)->retried);
        self::assertFalse($repo->findById($newId)->retried);
    }

    public function test_rate_limiter_blocks_after_max_and_clears(): void
    {
        $rl = new PdoRateLimiter($this->pdo);
        self::assertSame(0, $rl->attempt('k', 2, 60));
        self::assertSame(0, $rl->attempt('k', 2, 60));
        self::assertGreaterThan(0, $rl->attempt('k', 2, 60));
        self::assertGreaterThan(0, $rl->peek('k', 2, 60));
        $rl->clear('k');
        self::assertSame(0, $rl->peek('k', 2, 60));
    }
}
