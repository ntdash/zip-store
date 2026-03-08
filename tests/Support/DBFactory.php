<?php

namespace Tests\Support;

use Exception;

class DBFactory
{
    private const DB_RELPATH = '_data/database.sqlite';

    private static ?\PDO $pdo = null;

    /**
     * Checks whether a table with the given name exists in the SQLite database.
     *
     * @param string $table The name of the table to check.
     * @return bool `true` if a table with that name exists, `false` otherwise.
     */
    public function hasTable(string $table): bool
    {
        $query = "SELECT name FROM sqlite_master WHERE type='table' AND name=:table";

        $stmt = $this->pdo()->prepare($query);
        $stmt->execute([':table' => $table]);

        return (bool) $stmt->fetch();
    }

    /**
     * Provide a lazily-initialized PDO connection to the test SQLite database.
     *
     * @return \PDO The singleton PDO instance configured for the test SQLite database. The connection is persistent, uses associative arrays as the default fetch mode, and throws exceptions on errors.
     */
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

    /**
     * Resolve the filesystem path for the test SQLite database and ensure its parent directory exists.
     *
     * Computes the database file path from the class DB_RELPATH and creates the parent directory
     * if it does not exist.
     *
     * @return string The resolved database file path.
     * @throws \Exception If the resolved path is a directory or if creating the parent directory fails.
     */
    private function resolveDBPath(): string
    {
        $path = rtrim(tests_path(self::DB_RELPATH), DIRECTORY_SEPARATOR);

        if (is_dir($path)) {
            throw new Exception('Excepted a filepath but a dirpath provided');
        }

        $dirpath = dirname($path);

        if (! is_dir($dirpath)) {
            $created = mkdir($dirpath, recursive: true, permissions: 0o755);

            if (! $created) {
                throw new \Exception('Failed to created DB directory');
            }
        }

        return $path;
    }
}
