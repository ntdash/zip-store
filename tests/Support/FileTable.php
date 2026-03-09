<?php

namespace Tests\Support;

use Mimey\MimeTypes;

class FileTable extends Table
{
    /**
     * @var array<string>
     */
    private static array $columns = ['id', 'label', 'data', 'size', 'crc32digest', 'checksum', 'mime', 'created_at', 'updated_at'];

    /** @var null|array<string,mixed> */
    private static ?array $rawData = null;

    private static string $table = 'files';

    public function getColumns(): array
    {
        return self::$columns;
    }

    /**
     * @return array<string,mixed>
     */
    public function getRawData(): array
    {
        return self::$rawData ??= [
            'zap' => [
                'ext' => 'json',
                'content' => json_encode(['version' => '1.2', 'content' => ['lhs' => 'x--x3', 'rhs' => '>|px--x']]),
            ],
            'file1' => [
                'ext' => 'txt',
                'content' => 'file1 content',
            ],
            'file2' => [
                'ext' => 'txt',
                'content' => 'file2 content',
            ],
            'file3' => [
                'ext' => 'txt',
                'content' => 'file3 content',
            ],
        ];
    }

    /**
     * @param  array<string>  $excludedColumns
     * @return false|array<string,mixed>
     */
    public function getRowWithout(int $id, array $excludedColumns): false|array
    {
        $allowedColumns = $this->getColumns();
        $resolveColumns = array_diff($allowedColumns, array_intersect($allowedColumns, $excludedColumns));
        $query = sprintf(
            'SELECT %s FROM %s WHERE id = :id',
            implode(',', $resolveColumns),
            $this->getTable()
        );

        $stmt = $this->db->pdo()->prepare($query);

        $stmt->execute([':id' => $id]);

        return $stmt->fetch();
    }

    public function getTable(): string
    {
        return self::$table;
    }

    public function hasRow(int $id): bool
    {
        $query = "SELECT id FROM {$this->getTable()} WHERE id = :id";

        $stmt = $this->db->pdo()->prepare($query);
        $stmt->execute([':id' => $id]);

        return (bool) $stmt->fetch();
    }

    public static function load(): self
    {
        $table = new self(new DBFactory);

        if (false === $table->exists()) {
            $table->createTable();
        }

        $table->seed();

        return $table;
    }

    public static function new(): self
    {
        return new self(new DBFactory);
    }

    protected function createTable(): void
    {
        $query = "CREATE TABLE {$this->getTable()} (
            id          INTEGER PRIMARY KEY ,
            label       TEXT    NOT NULL,
            data        BLOB    NOT NULL,
            size        INTEGER NOT NULL DEFAULT 0,
            crc32digest TEXT    NOT NULL,
            mime        TEXT    NOT NULL,
            checksum    TEXT    NOT NULL,
            created_at  INTEGER NOT NULL DEFAULT (strftime('%s', 'now')),
            updated_at  INTEGER NOT NULL DEFAULT (strftime('%s', 'now'))
        )";

        $stmt = $this->db->pdo()->prepare($query);

        $stmt->execute();
    }

    protected function seed(): void
    {
        // get uptodate data
        $this->wipe();

        foreach ($this->getRawData() as $label => $details) {
            if (null == $details) {
                break;
            }

            $this->insert($label, $details['content'], $details['ext']);
        }
    }

    private function insert(string $label, string $content, string $ext): void
    {
        $query = "INSERT INTO {$this->getTable()}(label, data, size, crc32digest, checksum, mime) VALUES (:label, :data, :size, :crc32digest, :checksum, :mime)";
        $stmt = $this->db->pdo()->prepare($query);

        $params = [
            ':label' => $label,
            ':data' => $content,
            ':size' => strlen($content),
            ':crc32digest' => hash('crc32b', $content),
            ':checksum' => hash('sha256', $content),
            ':mime' => new MimeTypes()->getMimeType($ext),
        ];

        $stmt->execute($params);
    }
}
