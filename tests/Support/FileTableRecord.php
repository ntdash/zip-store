<?php

namespace Tests\Support;

use Tests\Exceptions\DBRowNotFoundException;

class FileTableRecord
{
    /**
     * @var array{'label':string,'size':int,'crc32digest':string,'created_at':int,'updated_at':int,'mime':string}
     * */
    public readonly array $meta;

    private bool $online = false;

    private FileTable $table;

    public function __construct(public readonly int $id)
    {
        $this->table = FileTable::new();

        $this->meta = self::loadMetadata($id, $this->table);

        // successfull loading of metadata result automatically into be online
        $this->online = true;
    }

    public function exists(bool $fresh = false): bool
    {
        if ($fresh) {
            return $this->online = $this->table->hasRow($this->id);
        }

        return $this->online;
    }

    public function getPartialData(int $offset, int $length): false|string
    {
        $query = "SELECT substr(data, :start, :length) as data from {$this->table->getTable()} where id = :id";

        $stmt = $this->table->prepareStatement($query);
        $stmt->execute([':id' => $this->id, ':start' => $offset + 1, ':length' => $length]);

        $result = $stmt->fetch();

        return $result ? $result['data'] : false;
    }

    /**
     * @return array{'label':string,'size':int,'crc32digest':string,'created_at':int,'updated_at':int,'mime':string}
     */
    private static function loadMetadata(int $id, FileTable $table): array
    {
        $row = $table->getRowWithout($id, ['data']);

        if (false === $row) {
            throw new DBRowNotFoundException($id, $table);
        }

        // @phpstan-ignore return.type
        return $row;
    }
}
