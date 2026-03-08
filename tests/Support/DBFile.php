<?php

namespace Tests\Support;

use Carbon\Carbon;
use Mimey\MimeTypes;
use ZipStore\Contracts\ZipStoreEntryFile;

class DBFile implements ZipStoreEntryFile
{
    private FileTableRecord $record;

    /**
     * Initialize the DBFile with the given file record identifier.
     *
     * @param int|string $id Numeric file record identifier used to load the underlying FileTableRecord.
     * @throws \InvalidArgumentException If the provided `$id` is not numeric.
     */
    public function __construct(int|string $id)
    {
        if (! \is_numeric($id)) {
            throw new \InvalidArgumentException('Expect numeric identifier');
        }

        $this->record = new FileTableRecord((int) $id);
    }

    /**
     * Provide an array representation used by PHP serialization containing the file identifier.
     *
     * @return array{id: string} An associative array with key `id` set to the file identifier.
     */
    public function __serialize(): array
    {
        return [
            'id' => $this->getIdentifier(),
        ];
    }

    / **
     * Get the file's identifier as a string.
     *
     * @return string The file identifier string.
     */
    public function __toString(): string
    {
        return $this->getIdentifier();
    }

    /**
         * Restore object state from serialized data containing a numeric `id`.
         *
         * Initializes the internal FileTableRecord using the `id` value from `$data`
         * after validating that `id` is numeric.
         *
         * @param array{id:mixed} $data Array with key `id` containing the record identifier.
         * @throws \InvalidArgumentException If `id` is not numeric.
         */
    public function __unserialize(array $data): void
    {
        $id = $data['id'];

        if (! \is_numeric($id)) {
            throw new \InvalidArgumentException('Expect numeric identifier');
        }

        $this->record = new FileTableRecord((int) $id);
    }

    /**
     * Report whether the file entry exists.
     *
     * @return bool `true` because this DBFile always represents an existing entry.
     */
    public function exists(): bool
    {
        return true;
    }

    /**
     * Get the file's access time by delegating to the modification time.
     *
     * @param bool $timestamp Ignored; present for interface compatibility.
     * @return int|Carbon The access time value (delegated to getMTime()). If an integer is returned it will be a UNIX timestamp; otherwise a Carbon instance in UTC.
     */
    public function getATime(bool $timestamp = false): int|Carbon
    {
        return $this->getMTime();
    }

    /**
     * Get the file's creation time as a UTC Carbon instance.
     *
     * The returned Carbon is constructed from the record's `created_at` meta value and is set to UTC.
     *
     * @param bool $timestamp Ignored; present for interface compatibility.
     * @return \Carbon\Carbon The creation time as a UTC Carbon instance.
     */
    public function getCTime(bool $timestamp = false): int|Carbon
    {
        return Carbon::createFromTimestampUTC(intval($this->record->meta['created_at']));
    }

    /**
     * Return the file extension corresponding to the record's MIME type.
     *
     * @return string The file extension (for example, "txt" or "jpg").
     * @throws \Exception If an extension cannot be determined from the record's MIME type.
     */
    public function getExtension(): string
    {
        $ext = new MimeTypes()->getExtension($this->record->meta['mime']);

        if (null === $ext) {
            throw new \Exception('Failed to retrieve extention');
        }

        return $ext;
    }

    /**
     * Get the filename label for this file.
     *
     * @return string The filename as stored in the underlying record's 'label' metadata.
     */
    public function getFilename(): string
    {
        return $this->record->meta['label'];
    }

    /**
     * Retrieve the current process group identifier.
     *
     * @return int The numeric group ID of the current process.
     */
    public function getGID(): int
    {
        return posix_getgid();
    }

    /**
     * Get the underlying file record's identifier.
     *
     * @return string The record identifier as a string.
     */
    public function getIdentifier(): string
    {
        return (string) $this->record->id;
    }

    /**
     * Get the file's modification time derived from the record's `updated_at` metadata.
     *
     * @param bool $timestamp Ignored by this implementation; retained for interface compatibility.
     * @return \Carbon\Carbon A Carbon instance (UTC) representing the modification time.
     */
    public function getMTime(bool $timestamp = false): int|Carbon
    {
        return Carbon::createFromTimestampUTC(intval($this->record->meta['updated_at']));
    }

    /**
     * Get the Unix permission mode used for the file entry.
     *
     * @return int The Unix file mode as an octal value (0644).
     */
    public function getMode(): int
    {
        return 0o644;
    }

    /**
     * Returns a 4-byte little-endian binary CRC32 digest derived from the record's `meta['crc32digest']`.
     *
     * @return string A 4-byte binary string containing the CRC32 digest in little-endian byte order.
     */
    public function getPackedCRC32Digest(): string
    {
        return pack('V', hexdec($this->record->meta['crc32digest']));
    }

    /**
     * Get the file size in bytes from the underlying record metadata.
     *
     * @return int The file size in bytes.
     */
    public function getSize(): int
    {
        return $this->record->meta['size'];
    }

    /**
     * Get the current process's user ID.
     *
     * @return int The numeric user identifier (UID) of the current process.
     */
    public function getUID(): int
    {
        return posix_getuid();
    }

    /**
     * Retrieve a shared PDO instance for database access used by the test support.
     *
     * Subsequent calls return the same PDO instance.
     *
     * @return \PDO The PDO connection instance. 
     */
    public static function pdo(): \PDO
    {
        static $pdo = null;

        return $pdo ??= (new DBFactory)->pdo();
    }

    /**
     * Retrieve a slice of the file's data starting at the given byte offset.
     *
     * @param int $offset Byte offset within the file where reading begins.
     * @param int $length Number of bytes to read.
     * @return string|false The requested data as a string, or `false` on failure.
     */
    public function read(int $offset, int $length): false|string
    {
        return $this->record->getPartialData($offset, $length);
    }
}
