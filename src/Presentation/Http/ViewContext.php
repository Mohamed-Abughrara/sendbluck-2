<?php
declare(strict_types=1);

namespace MailDesk\Presentation\Http;

/** Holds per-request values the view helpers need (CSRF token, old input, errors). */
final class ViewContext
{
    private static string $csrf = '';
    /** @var array<string,mixed> */
    private static array $old = [];
    /** @var array<string,string> */
    private static array $errors = [];

    public static function setCsrf(string $token): void
    {
        self::$csrf = $token;
    }

    public static function csrf(): string
    {
        return self::$csrf;
    }

    /** @param array<string,mixed> $old */
    public static function setOld(array $old): void
    {
        self::$old = $old;
    }

    public static function old(string $key, mixed $default = ''): mixed
    {
        return self::$old[$key] ?? $default;
    }

    /** @param array<string,string> $errors */
    public static function setErrors(array $errors): void
    {
        self::$errors = $errors;
    }

    public static function error(string $key): ?string
    {
        return self::$errors[$key] ?? null;
    }

    /** @return array<string,string> */
    public static function errors(): array
    {
        return self::$errors;
    }
}
