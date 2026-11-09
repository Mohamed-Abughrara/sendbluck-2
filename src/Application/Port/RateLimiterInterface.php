<?php
declare(strict_types=1);

namespace MailDesk\Application\Port;

interface RateLimiterInterface
{
    /** Count a hit if under the limit. Returns 0 when allowed, otherwise seconds until the window resets. */
    public function attempt(string $key, int $max, int $windowSeconds): int;

    /** Like attempt() but does not count. Returns 0 when under the limit, else seconds to wait. */
    public function peek(string $key, int $max, int $windowSeconds): int;

    /** Unconditionally count a hit. */
    public function hit(string $key, int $windowSeconds): void;

    public function clear(string $key): void;
}
