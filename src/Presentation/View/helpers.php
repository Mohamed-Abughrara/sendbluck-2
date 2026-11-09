<?php
declare(strict_types=1);

use MailDesk\Presentation\Http\ViewContext;

if (!function_exists('e')) {
    function e(mixed $v): string
    {
        return htmlspecialchars((string) $v, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }
}

if (!function_exists('csrf_field')) {
    function csrf_field(): string
    {
        return '<input type="hidden" name="_csrf" value="' . e(ViewContext::csrf()) . '">';
    }
}

if (!function_exists('csrf_token')) {
    function csrf_token(): string
    {
        return ViewContext::csrf();
    }
}

if (!function_exists('old')) {
    function old(string $key, mixed $default = ''): mixed
    {
        return ViewContext::old($key, $default);
    }
}

if (!function_exists('err')) {
    function err(string $key): ?string
    {
        return ViewContext::error($key);
    }
}

if (!function_exists('field_error')) {
    /** Renders an inline, accessible error message for a field (or nothing). */
    function field_error(string $key): string
    {
        $m = ViewContext::error($key);
        return $m === null ? '' : '<p class="field-error" id="err-' . e($key) . '" role="alert">' . e($m) . '</p>';
    }
}

if (!function_exists('t')) {
    /** Translate a key from lang/en.php; falls back to the key. */
    function t(string $key, array $replace = []): string
    {
        static $lang = null;
        $lang ??= require dirname(__DIR__, 3) . '/lang/en.php';
        $s = $lang[$key] ?? $key;
        foreach ($replace as $k => $v) {
            $s = str_replace('{' . $k . '}', (string) $v, $s);
        }
        return $s;
    }
}

if (!function_exists('recipients_label')) {
    /**
     * "Ali Ahmed (+2)" style summary of a message's recipients.
     *
     * @param list<array{name:string,email:string}> $to
     * @param list<array{name:string,email:string}> $cc
     */
    function recipients_label(array $to, array $cc = []): string
    {
        $all = array_merge($to, $cc);
        if ($all === []) {
            return '—';
        }
        $first = $all[0]['name'] !== '' ? $all[0]['name'] : $all[0]['email'];
        return count($all) > 1 ? $first . ' (+' . (count($all) - 1) . ')' : $first;
    }
}

if (!function_exists('url_with')) {
    /** @param array<string,mixed> $params */
    function url_with(string $base, array $params): string
    {
        $params = array_filter($params, static fn ($v): bool => $v !== '' && $v !== null);
        return $base . ($params === [] ? '' : '?' . http_build_query($params));
    }
}

if (!function_exists('pagination')) {
    /** @param array<string,mixed> $params query params to keep */
    function pagination(string $base, array $params, int $page, int $pages): string
    {
        if ($pages <= 1) {
            return '';
        }
        $link = static fn (int $p, string $label, string $aria = ''): string => '<a href="' . e(url_with($base, $params + ['page' => $p]))
            . '"' . ($p === $page ? ' aria-current="page" class="current"' : '') . ($aria !== '' ? ' aria-label="' . e($aria) . '"' : '') . '>' . $label . '</a>';
        $out = '<nav class="pagination" aria-label="Pagination">';
        if ($page > 1) {
            $out .= $link($page - 1, '&laquo;', 'Previous page');
        }
        $from = max(1, $page - 2);
        $to = min($pages, $page + 2);
        for ($i = $from; $i <= $to; $i++) {
            $out .= $link($i, (string) $i);
        }
        if ($page < $pages) {
            $out .= $link($page + 1, '&raquo;', 'Next page');
        }
        return $out . '</nav>';
    }
}

if (!function_exists('fmt_date')) {
    function fmt_date(?string $utc, string $format = 'd M H:i'): string
    {
        if ($utc === null || $utc === '') {
            return '—';
        }
        try {
            return (new DateTimeImmutable($utc, new DateTimeZone('UTC')))->format($format);
        } catch (Throwable) {
            return '—';
        }
    }
}
