<?php
declare(strict_types=1);

namespace MailDesk\Presentation\Http;

use MailDesk\Infrastructure\Session\NativeSession;

final class Request
{
    /** @var array<string,string> route variables */
    public array $params = [];

    /**
     * @param array<string,mixed>  $query
     * @param array<string,mixed>  $post
     * @param array<string,mixed>  $server
     * @param list<string>         $trustedProxies
     */
    public function __construct(
        public readonly string $method,
        public readonly string $path,
        private array $query,
        private array $post,
        private array $server,
        public readonly NativeSession $session,
        private array $trustedProxies = [],
    ) {
    }

    /** @param list<string> $trustedProxies */
    public static function fromGlobals(NativeSession $session, array $trustedProxies = []): self
    {
        $uri = (string) ($_SERVER['REQUEST_URI'] ?? '/');
        $path = rawurldecode((string) parse_url($uri, PHP_URL_PATH));
        $path = '/' . trim($path, '/');
        return new self(strtoupper((string) ($_SERVER['REQUEST_METHOD'] ?? 'GET')), $path, $_GET, $_POST, $_SERVER, $session, $trustedProxies);
    }

    public function query(string $key, mixed $default = null): mixed
    {
        return $this->query[$key] ?? $default;
    }

    public function post(?string $key = null, mixed $default = null): mixed
    {
        return $key === null ? $this->post : ($this->post[$key] ?? $default);
    }

    public function str(string $key, string $default = ''): string
    {
        $v = $this->post[$key] ?? $this->query[$key] ?? $default;
        return is_string($v) ? $v : $default;
    }

    /** @return list<int> positive integer ids from an array input (post or query) */
    public function intList(string $key): array
    {
        $v = $this->post[$key] ?? $this->query[$key] ?? [];
        if (!is_array($v)) {
            $v = [$v];
        }
        $out = [];
        foreach ($v as $x) {
            if (is_scalar($x) && ctype_digit((string) $x) && (int) $x > 0) {
                $out[] = (int) $x;
            }
        }
        return array_values(array_unique($out));
    }

    public function header(string $name): ?string
    {
        $key = 'HTTP_' . strtoupper(str_replace('-', '_', $name));
        $v = $this->server[$key] ?? null;
        return is_string($v) ? $v : null;
    }

    public function userId(): int
    {
        return (int) $this->session->userId();
    }

    public function isHttps(): bool
    {
        if (($this->server['HTTPS'] ?? '') !== '' && $this->server['HTTPS'] !== 'off') {
            return true;
        }
        $remote = (string) ($this->server['REMOTE_ADDR'] ?? '');
        return in_array($remote, $this->trustedProxies, true)
            && strtolower((string) $this->header('X-Forwarded-Proto')) === 'https';
    }

    public function ip(): string
    {
        $remote = (string) ($this->server['REMOTE_ADDR'] ?? '0.0.0.0');
        if (in_array($remote, $this->trustedProxies, true)) {
            $fwd = trim(explode(',', (string) $this->header('X-Forwarded-For'))[0]);
            if (filter_var($fwd, FILTER_VALIDATE_IP) !== false) {
                return $fwd;
            }
        }
        return $remote;
    }

    public function wantsJson(): bool
    {
        return str_starts_with($this->path, '/api/')
            || str_contains((string) $this->header('Accept'), 'application/json');
    }
}
