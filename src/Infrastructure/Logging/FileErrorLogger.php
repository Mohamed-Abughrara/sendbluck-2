<?php
declare(strict_types=1);

namespace MailDesk\Infrastructure\Logging;

/** JSON-lines logger with secret redaction (SEC-17, NFR-13). */
final class FileErrorLogger
{
    private const REDACT_KEYS = ['password', 'smtp_password', 'app_key', '_csrf', 'token', 'secret', 'authorization'];
    private string $requestId;

    public function __construct(private string $file, private string $level = 'warning')
    {
        $this->requestId = bin2hex(random_bytes(6));
    }

    public function requestId(): string
    {
        return $this->requestId;
    }

    /** @param array<string,mixed> $context */
    public function error(string $message, array $context = []): void
    {
        $this->write('error', $message, $context);
    }

    /** @param array<string,mixed> $context */
    public function warning(string $message, array $context = []): void
    {
        $this->write('warning', $message, $context);
    }

    /** @param array<string,mixed> $context */
    private function write(string $level, string $message, array $context): void
    {
        $order = ['debug' => 0, 'info' => 1, 'warning' => 2, 'error' => 3];
        if (($order[$level] ?? 3) < ($order[$this->level] ?? 2)) {
            return;
        }
        $line = json_encode([
            'time' => gmdate('c'),
            'level' => $level,
            'request_id' => $this->requestId,
            'message' => self::redactText($message),
            'context' => self::redact($context),
        ], JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE);
        $dir = dirname($this->file);
        if (!is_dir($dir)) {
            @mkdir($dir, 0750, true);
        }
        @file_put_contents($this->file, $line . PHP_EOL, FILE_APPEND | LOCK_EX);
    }

    /**
     * @param array<mixed> $data
     * @return array<mixed>
     */
    public static function redact(array $data): array
    {
        foreach ($data as $k => $v) {
            if (is_string($k) && in_array(strtolower($k), self::REDACT_KEYS, true)) {
                $data[$k] = '[redacted]';
            } elseif (is_array($v)) {
                $data[$k] = self::redact($v);
            } elseif (is_string($v)) {
                $data[$k] = self::redactText($v);
            }
        }
        return $data;
    }

    public static function redactText(string $s): string
    {
        $s = preg_replace('/(password|smtp_password\w*|app_key|_csrf)\s*[=:]\s*\S+/i', '$1=[redacted]', $s) ?? $s;
        return preg_replace('/base64:[A-Za-z0-9+\/=]{20,}/', 'base64:[redacted]', $s) ?? $s;
    }
}
