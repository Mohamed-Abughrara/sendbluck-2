<?php
declare(strict_types=1);

namespace MailDesk\Presentation\Http;

use MailDesk\Infrastructure\Session\NativeSession;

final class Response
{
    /** @var list<array{string,string}> */
    private array $flash = [];
    /** @var array<string,mixed>|null */
    private ?array $old = null;
    /** @var array<string,string>|null */
    private ?array $errors = null;

    /** @param array<string,string> $headers */
    private function __construct(public int $status, public string $body, public array $headers = [])
    {
    }

    public static function html(string $body, int $status = 200): self
    {
        return new self($status, $body, ['Content-Type' => 'text/html; charset=UTF-8']);
    }

    /** @param array<mixed> $data */
    public static function json(array $data, int $status = 200): self
    {
        return new self($status, json_encode($data, JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE) ?: '{}', ['Content-Type' => 'application/json; charset=UTF-8']);
    }

    public static function redirect(string $location, int $status = 302): self
    {
        return new self($status, '', ['Location' => $location]);
    }

    public function withStatus(int $status): self
    {
        $this->status = $status;
        return $this;
    }

    public function withHeader(string $name, string $value): self
    {
        $this->headers[$name] = $value;
        return $this;
    }

    public function withFlash(string $type, string $message): self
    {
        $this->flash[] = [$type, $message];
        return $this;
    }

    /** @param array<string,mixed> $old */
    public function withOldInput(array $old): self
    {
        // never keep secrets or tokens in the session
        unset($old['_csrf'], $old['smtp_password'], $old['password']);
        $this->old = $old;
        return $this;
    }

    /** @param array<string,string> $errors */
    public function withErrors(array $errors): self
    {
        $this->errors = $errors;
        return $this;
    }

    public function send(NativeSession $session): void
    {
        foreach ($this->flash as [$type, $msg]) {
            $session->flash($type, $msg);
        }
        if ($this->old !== null) {
            $session->set('_old', $this->old);
        }
        if ($this->errors !== null) {
            $session->set('_errors', $this->errors);
        }
        if (session_status() === PHP_SESSION_ACTIVE) {
            session_write_close();
        }

        http_response_code($this->status);
        foreach ($this->headers as $k => $v) {
            header($k . ': ' . str_replace(["\r", "\n"], '', $v));
        }
        echo $this->body;
    }
}
