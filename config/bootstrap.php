<?php
declare(strict_types=1);

use MailDesk\Infrastructure\Container\Container;

/**
 * Shared bootstrap for the web front controller and the CLI.
 *
 * @return array{0: Container, 1: array<string,mixed>}
 */
$root = dirname(__DIR__);
require $root . '/vendor/autoload.php';

date_default_timezone_set('UTC');
mb_internal_encoding('UTF-8');

$settings = (require $root . '/config/settings.php')($root);

ini_set('display_errors', $settings['is_production'] ? '0' : '1');
ini_set('log_errors', '1');
ini_set('error_log', $root . '/storage/logs/php-error.log');
error_reporting(E_ALL);

$container = new Container();
(require $root . '/config/container.php')($container, $settings);

return [$container, $settings];
