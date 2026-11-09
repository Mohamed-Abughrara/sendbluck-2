<?php
declare(strict_types=1);

/**
 * Reads the environment (.env) into a typed settings array.
 *
 * @return array<string,mixed>
 */
return static function (string $root): array {
    if (is_file($root . '/.env')) {
        Dotenv\Dotenv::createImmutable($root)->safeLoad();
    }

    $env = static fn (string $k, ?string $default = null): ?string => $_ENV[$k] ?? $_SERVER[$k] ?? (getenv($k) !== false ? getenv($k) : $default);

    $appEnv = $env('APP_ENV', 'production');
    $appKey = $env('APP_KEY');
    if ($appKey === null || $appKey === '' || str_contains($appKey, 'REPLACE_WITH')) {
        throw new RuntimeException('APP_KEY is missing. Run: php bin/console key:generate');
    }
    MailDesk\Infrastructure\Security\SodiumEncryptor::parseKey($appKey); // throws if wrong length

    $driver = $env('DB_DRIVER', 'sqlite');
    $dbPath = (string) $env('DB_PATH', $root . '/storage/maildesk.sqlite');
    if (!preg_match('#^([A-Za-z]:)?[\\\\/]#', $dbPath)) {
        $dbPath = $root . '/' . $dbPath;
    }

    return [
        'root' => $root,
        'app_env' => $appEnv,
        'is_production' => $appEnv === 'production',
        'app_url' => $env('APP_URL', 'http://localhost:8080'),
        'app_key' => $appKey,
        'db' => [
            'driver' => $driver,
            'host' => $env('DB_HOST', '127.0.0.1'),
            'port' => (int) $env('DB_PORT', '3306'),
            'name' => $env('DB_NAME', 'maildesk'),
            'user' => $env('DB_USER', 'maildesk'),
            'password' => $env('DB_PASSWORD', ''),
            'path' => $dbPath,
        ],
        'session_idle' => (int) $env('SESSION_IDLE_MINUTES', '30') * 60,
        'session_absolute' => (int) $env('SESSION_ABSOLUTE_HOURS', '8') * 3600,
        'rate_login_max' => (int) $env('RATE_LOGIN_MAX', '5'),
        'rate_login_window' => (int) $env('RATE_LOGIN_WINDOW_MIN', '15') * 60,
        'rate_send_per_min' => (int) $env('RATE_SEND_PER_MIN', '10'),
        'rate_send_per_day' => (int) $env('RATE_SEND_PER_DAY', '200'),
        'mail_max_recipients' => (int) $env('MAIL_MAX_RECIPIENTS', '50'),
        'mail_timeout' => (int) $env('MAIL_TIMEOUT_SECONDS', '10'),
        'trusted_proxies' => array_values(array_filter(array_map('trim', explode(',', (string) $env('TRUSTED_PROXIES', ''))))),
        'log_level' => $env('LOG_LEVEL', 'warning'),
    ];
};
