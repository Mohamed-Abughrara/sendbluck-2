<?php
declare(strict_types=1);

// Front controller: the only web-exposed PHP file.
try {
    [$container] = require dirname(__DIR__) . '/config/bootstrap.php';
    $container->get(MailDesk\Presentation\Http\Kernel::class)->run();
} catch (Throwable $e) {
    // Boot failure (e.g. missing APP_KEY): never leak details in production.
    error_log('MailDesk boot failure: ' . $e->getMessage());
    http_response_code(500);
    header('Content-Type: text/plain; charset=UTF-8');
    $debug = (($_ENV['APP_ENV'] ?? getenv('APP_ENV') ?: 'production') !== 'production');
    echo $debug ? 'Boot failure: ' . $e->getMessage() : 'Service unavailable.';
}
