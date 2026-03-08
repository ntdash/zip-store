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

    /**
     * Create a FileTableRecord for the given id and load its metadata into the instance.
     *
     * Initializes the associated FileTable, populates the read-only `meta` array for the record,
     * and marks the instance as online when metadata is loaded successfully.
     *
     * @param int $id The identifier of the file table row to load.
     * @throws \Tests\Exceptions\DBRowNotFoundException If no row exists for the given id.
     */
    public function __construct(public readonly int $id)
    {
        $this->table = FileTable::new();

        $this->meta = self::loadMetadata($id, $this->table);

        // successfull loading of metadata result automatically into be online
        $this->online = true;
    }

    /**
     * Report whether the record currently exists in the underlying FileTable.
     *
     * When `$fresh` is true, the existence state is refreshed by querying the table;
     * otherwise the last known state is returned.
     *
     * @param bool $fresh If true, perform a fresh existence check against the table.
     * @return bool `true` if the record exists, `false` otherwise.
     */
    public function exists(bool $fresh = false): bool
    {
        if ($fresh) {
            return $this->online = $this->table->hasRow($this->id);
        }

        return $this->online;
    }

    /**
     * Retrieve a slice of the record's binary/text `data` column.
     *
     * @param int $offset Zero-based start position within the `data` value.
     * @param int $length Number of bytes/characters to retrieve starting at `$offset`.
     * @return string|false `string` containing the requested substring, `false` if the record (or its data) was not found.
     */
    public function getPartialData(int $offset, int $length): false|string
    {
        $query = "SELECT substr(data, :start, :length) as data from {$this->table->getTable()} where id = :id";

        $stmt = $this->table->prepareStatment($query);
        $stmt->execute(['id' => $this->id, 'offset' => $offset + 1, 'length' => $length]);

        $result = $stmt->fetch();

        return $result ? $result['data'] : false;
    }

    /**
     * Load metadata for the given file record id from the FileTable.
     *
     * @param int $id The record id.
     * @param FileTable $table The FileTable instance to query.
     * @return array{'label':string,'size':int,'crc32digest':string,'created_at':int,'updated_at':int,'mime':string} Associative array with keys: `label`, `size`, `crc32digest`, `created_at`, `updated_at`, and `mime`.
     * @throws DBRowNotFoundException If no row exists for the provided id.
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
