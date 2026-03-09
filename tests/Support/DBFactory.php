<?php

namespace Tests\Support;

use Exception;

class DBFactory
{
    private const DB_RELPATH = '_data/database.sqlite';

    private static ?\PDO $pdo = null;

    public function hasTable(string $table): bool
    {
        $query = "SELECT name FROM sqlite_master WHERE type='table' AND name=:table";

        $stmt = $this->pdo()->prepare($query);
        $stmt->execute([':table' => $table]);

        return (bool) $stmt->fetch();
    }

    public function pdo(): \PDO
    {
        if (null === self::$pdo) {
            $dsn = sprintf('sqlite:%s', $this->resolveDBPath());
            $options = [
                \PDO::ATTR_PERSISTENT => true,
                \PDO::FETCH_DEFAULT => \PDO::FETCH_ASSOC,
                \PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION,
            ];

            self::$pdo = new \PDO($dsn, null, null, $options);
        }

        return self::$pdo;

    }

    private function resolveDBPath(): string
    {
        $path = \rtrim(\tests_path(self::DB_RELPATH), DIRECTORY_SEPARATOR);

        if (\is_dir($path)) {
            throw new Exception('Expected a filepath but a dirpath provided');
        }

        $dirpath = \dirname($path);

        if (! \is_dir($dirpath)) {
            $created = \mkdir($dirpath, recursive: true, permissions: 0o755);

            if (! $created) {
                throw new \Exception('Failed to created DB directory');
            }
        }

        return $path;
    }
}
