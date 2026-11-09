<?php
declare(strict_types=1);

namespace MailDesk\Infrastructure\Session;

/** Hardened wrapper around PHP native sessions (SEC-02, SEC-03). */
final class NativeSession
{
    /** True when the last validate() call destroyed a session because of a timeout. */
    public bool $expired = false;

    public function __construct(
        private bool $secureCookie,
        private int $idleSeconds = 1800,
        private int $absoluteSeconds = 28800,
    ) {
    }

    public function start(): void
    {
        if (session_status() === PHP_SESSION_ACTIVE) {
            return;
        }
        ini_set('session.use_strict_mode', '1');
        ini_set('session.use_only_cookies', '1');
        ini_set('session.use_trans_sid', '0');
        session_name('maildesk_sid');
        session_set_cookie_params([
            'lifetime' => 0,
            'path' => '/',
            'secure' => $this->secureCookie,
            'httponly' => true,
            'samesite' => 'Strict',
        ]);
        session_start();
    }

    /** Returns true if the session is authenticated and still within its timeouts. Destroys it otherwise. */
    public function validate(): bool
    {
        if (!$this->has('user_id')) {
            return false;
        }
        $now = time();
        $created = (int) $this->get('created', 0);
        $last = (int) $this->get('last_activity', 0);
        if ($now - $last > $this->idleSeconds || $now - $created > $this->absoluteSeconds) {
            $this->destroy();
            $this->start();
            $this->expired = true;
            return false;
        }
        $this->set('last_activity', $now);
        return true;
    }

    public function login(int $userId): void
    {
        session_regenerate_id(true);
        $_SESSION = [];
        $this->set('user_id', $userId);
        $this->set('created', time());
        $this->set('last_activity', time());
    }

    public function userId(): ?int
    {
        $v = $this->get('user_id');
        return $v === null ? null : (int) $v;
    }

    public function get(string $key, mixed $default = null): mixed
    {
        return $_SESSION[$key] ?? $default;
    }

    public function set(string $key, mixed $value): void
    {
        $_SESSION[$key] = $value;
    }

    public function has(string $key): bool
    {
        return isset($_SESSION[$key]);
    }

    public function remove(string $key): void
    {
        unset($_SESSION[$key]);
    }

    public function flash(string $type, string $message): void
    {
        $_SESSION['_flash'][] = ['type' => $type, 'message' => $message];
    }

    /** @return list<array{type:string,message:string}> */
    public function pullFlash(): array
    {
        $f = $_SESSION['_flash'] ?? [];
        unset($_SESSION['_flash']);
        return $f;
    }

    public function destroy(): void
    {
        if (session_status() !== PHP_SESSION_ACTIVE) {
            return;
        }
        $_SESSION = [];
        $p = session_get_cookie_params();
        setcookie(session_name(), '', [
            'expires' => time() - 3600,
            'path' => $p['path'],
            'secure' => $p['secure'],
            'httponly' => true,
            'samesite' => 'Strict',
        ]);
        session_destroy();
    }
}
