<?php
declare(strict_types=1);

namespace MailDesk\Infrastructure\Security;

use MailDesk\Application\Port\RateLimiterInterface;
use PDO;

/** Fixed-window counters stored in the rate_limits table. */
final class PdoRateLimiter implements RateLimiterInterface
{
    public function __construct(private PDO $pdo)
    {
    }

    public function attempt(string $key, int $max, int $windowSeconds): int
    {
        $row = $this->current($key, $windowSeconds);
        if ($row['hits'] >= $max) {
            return max(1, $row['start'] + $windowSeconds - time());
        }
        $this->write($key, $row['hits'] + 1, $row['start']);
        return 0;
    }

    public function peek(string $key, int $max, int $windowSeconds): int
    {
        $row = $this->current($key, $windowSeconds);
        return $row['hits'] >= $max ? max(1, $row['start'] + $windowSeconds - time()) : 0;
    }

    public function hit(string $key, int $windowSeconds): void
    {
        $row = $this->current($key, $windowSeconds);
        $this->write($key, $row['hits'] + 1, $row['start']);
    }

    public function clear(string $key): void
    {
        $this->pdo->prepare('DELETE FROM rate_limits WHERE rl_key = ?')->execute([$this->k($key)]);
    }

    /** @return array{hits: int, start: int} */
    private function current(string $key, int $window): array
    {
        $st = $this->pdo->prepare('SELECT hits, window_start FROM rate_limits WHERE rl_key = ?');
        $st->execute([$this->k($key)]);
        $row = $st->fetch();
        $now = time();
        if (!$row || (int) $row['window_start'] + $window <= $now) {
            return ['hits' => 0, 'start' => $now];
        }
        return ['hits' => (int) $row['hits'], 'start' => (int) $row['window_start']];
    }

    private function write(string $key, int $hits, int $start): void
    {
        $k = $this->k($key);
        $up = $this->pdo->prepare('UPDATE rate_limits SET hits = ?, window_start = ? WHERE rl_key = ?');
        $up->execute([$hits, $start, $k]);
        if ($up->rowCount() === 0) {
            try {
                $this->pdo->prepare('INSERT INTO rate_limits (rl_key, hits, window_start) VALUES (?, ?, ?)')->execute([$k, $hits, $start]);
            } catch (\PDOException) {
                $up->execute([$hits, $start, $k]); // lost a race: update instead
            }
        }
    }

    private function k(string $key): string
    {
        return strlen($key) <= 190 ? $key : hash('sha256', $key);
    }
}
