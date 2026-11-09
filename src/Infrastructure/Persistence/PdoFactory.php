<?php
declare(strict_types=1);

namespace MailDesk\Infrastructure\Persistence;

use PDO;

final class PdoFactory
{
    /** @param array<string,mixed> $db */
    public static function create(array $db): PDO
    {
        $options = [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        ];

        if (($db['driver'] ?? 'sqlite') === 'mysql') {
            $dsn = sprintf(
                'mysql:host=%s;port=%d;dbname=%s;charset=utf8mb4',
                $db['host'],
                $db['port'],
                $db['name'],
            );
            $pdo = new PDO($dsn, (string) $db['user'], (string) $db['password'], $options);
            $pdo->exec("SET time_zone = '+00:00'");
            return $pdo;
        }

        $path = (string) $db['path'];
        $dir = dirname($path);
        if (!is_dir($dir)) {
            mkdir($dir, 0750, true);
        }
        $pdo = new PDO('sqlite:' . $path, null, null, $options);
        $pdo->exec('PRAGMA foreign_keys = ON');
        $pdo->exec('PRAGMA busy_timeout = 5000');
        return $pdo;
    }

    public static function driver(PDO $pdo): string
    {
        return (string) $pdo->getAttribute(PDO::ATTR_DRIVER_NAME);
    }
}
