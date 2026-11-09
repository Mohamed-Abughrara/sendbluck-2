<?php
declare(strict_types=1);

namespace MailDesk\Infrastructure\Container;

/** Minimal PSR-11-style service container. */
final class Container
{
    /** @var array<string, array{callable, bool}> */
    private array $factories = [];
    /** @var array<string, mixed> */
    private array $instances = [];

    public function singleton(string $id, callable $factory): void
    {
        $this->factories[$id] = [$factory, true];
    }

    public function bind(string $id, callable $factory): void
    {
        $this->factories[$id] = [$factory, false];
    }

    public function has(string $id): bool
    {
        return isset($this->factories[$id]) || isset($this->instances[$id]);
    }

    public function get(string $id): mixed
    {
        if (array_key_exists($id, $this->instances)) {
            return $this->instances[$id];
        }
        if (!isset($this->factories[$id])) {
            throw new \RuntimeException("Service not registered: {$id}");
        }
        [$factory, $shared] = $this->factories[$id];
        $obj = $factory($this);
        if ($shared) {
            $this->instances[$id] = $obj;
        }
        return $obj;
    }
}
