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

    /**
     * Returns the list of column names for the test files table.
     *
     * @return string[] An ordered array of column names present in the table.
     */
    public function getColumns(): array
    {
        return self::$columns;
    }

    /**
     * Provide sample file fixture data keyed by label.
     *
     * @return array<string,array{ext:string,content:string}> Associative array where each key is a label and each value is an array with `ext` (file extension) and `content` (file data).
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
         * Fetches a single row by id excluding the specified columns.
         *
         * The result will contain only the table columns that are not listed in `$columnExcluded`.
         *
         * @param array<string> $columnExcluded Column names to omit from the selected result.
         * @return false|array<string,mixed> An associative array of column => value for the matched row, or `false` if no row was found.
         */
    public function getRowWithout(int $id, array $columnExcluded): false|array
    {
        $resolveColumns = array_diff($this->getColumns(), $columnExcluded);
        $query = sprintf(
            'SELECT %s FROM %s WHERE id = :id',
            implode(',', $resolveColumns),
            $this->getTable()
        );

        $stmt = $this->db->pdo()->prepare($query);

        $stmt->execute([':id' => $id]);

        return $stmt->fetch();
    }

    /**
     * Get the database table name for this table class.
     *
     * @return string The table name.
     */
    public function getTable(): string
    {
        return self::$table;
    }

    /**
     * Determine whether a row with the specified id exists in the table.
     *
     * @param int $id The row identifier to look up.
     * @return bool `true` if a row with the given id exists in the table, `false` otherwise.
     */
    public function hasRow(int $id): bool
    {
        $query = "SELECT id FROM {$this->getTable()} WHERE id = :id";

        $stmt = $this->db->pdo()->prepare($query);
        $result = $stmt->execute([':id' => $id]);

        return (bool) $result;
    }

    /**
     * Create and return a FileTable instance whose underlying table is present and populated.
     *
     * @return self The FileTable instance with its database table created if it did not exist and seeded with initial data.
     */
    public static function load(): self
    {
        $table = new self(new DBFactory);

        if (false === $table->exists()) {
            $table->createTable();
        }

        $table->seed();

        return $table;
    }

    /**
     * Create a FileTable instance configured with a default DBFactory.
     *
     * @return self A new FileTable instance.
     */
    public static function new(): self
    {
        return new self(new DBFactory);
    }

    /**
         * Prepare and return a PDO statement for the given SQL query.
         *
         * @param  array<int,int>  $options Driver-specific options to pass to PDO::prepare (e.g., PDO::ATTR_CURSOR).
         * @return \PDOStatement The prepared PDO statement.
         */
    public function prepareStatment(string $query, array $options = []): \PDOStatement
    {
        return $this->db->pdo()->prepare($query, $options);
    }

    /**
     * Create the table used by this FileTable instance in the configured database.
     *
     * The table is created with the following columns:
     * - id: INTEGER PRIMARY KEY
     * - label: TEXT NOT NULL
     * - data: BLOB NOT NULL
     * - size: INTEGER NOT NULL DEFAULT 0
     * - crc32digest: TEXT NOT NULL
     * - mime: TEXT NOT NULL
     * - checksum: TEXT NOT NULL
     * - created_at: INTEGER NOT NULL DEFAULT current Unix time
     * - updated_at: INTEGER NOT NULL DEFAULT current Unix time
     */
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

    /**
     * Populate the files table with the class's predefined raw data.
     *
     * This clears existing rows, then inserts each entry from getRawData() into the table.
     * If an entry's value is null, seeding stops at that entry. If an entry is missing
     * extension or content, a default text entry is used.
     *
     * @return void
     */
    protected function seed(): void
    {
        // get uptodate data
        $this->wipe();

        foreach ($this->getRawData() as $label => $details) {
            $details ??= [
                'ext' => 'txt',
                'content' => sprintf('%s content', $label),
            ];

            if (null == $details) {
                break;
            }

            $this->insert($label, $details['content'], $details['ext']);
        }
    }

    /**
     * Insert a file record into the files table with computed metadata.
     *
     * Computes the content size, CRC32 and SHA-256 checksums, resolves the MIME type from the file extension,
     * and inserts a row with label, data, size, crc32digest, checksum, and mime.
     *
     * @param string $label Human-readable label for the file.
     * @param string $content Raw file content to store in the data column.
     * @param string $ext File extension used to determine the MIME type.
     */
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
