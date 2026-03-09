<?php

namespace Tests\Support;

abstract class Table
{
    public function __construct(protected readonly DBFactory $db) {}

    public function exists(): bool
    {
        return $this->db->hasTable($this->getTable());
    }

    /**
     * @return array<string>
     */
    abstract public function getColumns(): array;

    abstract public function getTable(): string;

    protected function wipe(): bool
    {
        $query = "DELETE FROM {$this->getTable()}";

        $stmt = $this->db->pdo()->prepare($query);

        return $stmt->execute();
    }

    /**
     * @param  array<int,int>  $options
     */
    public function prepareStatement(string $query, array $options = []): \PDOStatement
    {
        return $this->db->pdo()->prepare($query, $options);
    }
}
