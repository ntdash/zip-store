<?php

namespace Tests\Support;

abstract class Table
{
    /**
 * Create a Table helper with a DBFactory used for database operations.
 */
public function __construct(protected readonly DBFactory $db) {}

    /**
     * Determines whether the database table associated with this instance exists.
     *
     * @return bool `true` if the table exists in the database, `false` otherwise.
     */
    public function exists(): bool
    {
        return $this->db->hasTable($this->getTable());
    }

    /**
 * List column names for the table.
 *
 * @return array<string> An array of column name strings.
 */
    abstract public function getColumns(): array;

    /**
 * Provide the database table name for this test table.
 *
 * @return string The database table name.
 */
abstract public function getTable(): string;

    /**
     * Remove all rows from the database table associated with this Table instance.
     *
     * Executes a DELETE FROM statement for the table returned by getTable().
     *
     * @return bool `true` if the delete statement executed successfully, `false` otherwise.
     */
    protected function wipe(): bool
    {
        $query = "DELETE FROM {$this->getTable()}";

        $stmt = $this->db->pdo()->prepare($query);

        return $stmt->execute();
    }
}
