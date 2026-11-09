<?php
declare(strict_types=1);

namespace MailDesk\Infrastructure\Persistence;

use PDO;

/**
 * Applies database/migrations/*.sql in order. Portable tokens are replaced per driver; lines prefixed
 * with "--sqlite:" or "--mysql:" are only used for that driver.
 */
final class Migrator
{
    public function __construct(private PDO $pdo, private string $dir)
    {
    }

    /** @return list<string> names of newly applied migrations */
    public function run(): array
    {
        $driver = PdoFactory::driver($this->pdo);
        $this->pdo->exec(
            'CREATE TABLE IF NOT EXISTS migrations (name VARCHAR(190) NOT NULL PRIMARY KEY, applied_at VARCHAR(19) NOT NULL)'
        );
        $done = $this->pdo->query('SELECT name FROM migrations')->fetchAll(PDO::FETCH_COLUMN);

        $files = glob($this->dir . '/*.sql') ?: [];
        sort($files);
        $applied = [];
        foreach ($files as $file) {
            $name = basename($file);
            if (in_array($name, $done, true)) {
                continue;
            }
            foreach ($this->statements((string) file_get_contents($file), $driver) as $sql) {
                $this->pdo->exec($sql);
            }
            $this->pdo->prepare('INSERT INTO migrations (name, applied_at) VALUES (?, ?)')
                ->execute([$name, gmdate('Y-m-d H:i:s')]);
            $applied[] = $name;
        }
        return $applied;
    }

    /** @return list<string> */
    private function statements(string $sql, string $driver): array
    {
        $tokens = $driver === 'mysql'
            ? [
                '{{PK}}' => 'INT UNSIGNED AUTO_INCREMENT PRIMARY KEY',
                '{{BIGPK}}' => 'BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY',
                '{{INT}}' => 'INT UNSIGNED',
                '{{LONGTEXT}}' => 'MEDIUMTEXT',
                '{{ENGINE}}' => ' ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci',
            ]
            : [
                '{{PK}}' => 'INTEGER PRIMARY KEY AUTOINCREMENT',
                '{{BIGPK}}' => 'INTEGER PRIMARY KEY AUTOINCREMENT',
                '{{INT}}' => 'INTEGER',
                '{{LONGTEXT}}' => 'TEXT',
                '{{ENGINE}}' => '',
            ];

        $lines = [];
        foreach (preg_split('/\R/', $sql) ?: [] as $line) {
            if (str_starts_with($line, '--')) {
                if (str_starts_with($line, "--{$driver}:")) {
                    $lines[] = substr($line, strlen("--{$driver}:"));
                }
                continue;
            }
            $lines[] = $line;
        }
        $sql = strtr(implode("\n", $lines), $tokens);

        $stmts = array_map('trim', preg_split('/;\s*(?:\n|$)/', $sql) ?: []);
        return array_values(array_filter($stmts, static fn (string $s): bool => $s !== ''));
    }
}
