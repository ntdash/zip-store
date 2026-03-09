<?php

namespace Tests\Support;

use Carbon\Carbon;
use Mimey\MimeTypes;
use ZipStore\Contracts\ZipStoreEntryFile;

class DBFile implements ZipStoreEntryFile
{
    private FileTableRecord $record;

    /**
     * @return void
     */
    public function __construct(int|string $id)
    {
        if (! \is_numeric($id)) {
            throw new \InvalidArgumentException('Expect numeric identifier');
        }

        $this->record = new FileTableRecord((int) $id);
    }

    public function __serialize(): array
    {
        return [
            'id' => $this->getIdentifier(),
        ];
    }

    public function __toString(): string
    {
        return $this->getIdentifier();
    }

    /**
     * @param  array{id:mixed}  $data
     */
    public function __unserialize(array $data): void
    {
        $id = $data['id'];

        if (! \is_numeric($id)) {
            throw new \InvalidArgumentException('Expect numeric identifier');
        }

        $this->record = new FileTableRecord((int) $id);
    }

    public function exists(): bool
    {
        return $this->record->exists();
    }

    public function getATime(bool $timestamp = false): int|Carbon
    {
        return $this->getMTime($timestamp);
    }

    public function getCTime(bool $timestamp = false): int|Carbon
    {
        $time = intval($this->record->meta['created_at']);

        if ($timestamp) {
            return $time;
        }

        return Carbon::createFromTimestampUTC($time);
    }

    public function getExtension(): string
    {
        $ext = new MimeTypes()->getExtension($this->record->meta['mime']);

        if (null === $ext) {
            throw new \Exception('Failed to retrieve extention');
        }

        return $ext;
    }

    public function getFilename(): string
    {
        return $this->record->meta['label'];
    }

    public function getGID(): int
    {
        return function_exists("posix_getgid") ? posix_getgid() : 1000;
    }

    public function getIdentifier(): string
    {
        return (string) $this->record->id;
    }

    public function getMTime(bool $timestamp = false): int|Carbon
    {
        $time = intval($this->record->meta['updated_at']);

        if ($timestamp) {
            return $time;
        }

        return Carbon::createFromTimestampUTC($time);
    }

    public function getMode(): int
    {
        return 0o644;
    }

    public function getPackedCRC32Digest(): string
    {
        return pack('V', hexdec($this->record->meta['crc32digest']));
    }

    public function getSize(): int
    {
        return $this->record->meta['size'];
    }

    public function getUID(): int
    {
        return function_exists("posix_getuid") ? posix_getuid(): 1000;
    }

    public static function pdo(): \PDO
    {
        static $pdo = null;

        return $pdo ??= (new DBFactory)->pdo();
    }

    public function read(int $offset, int $length): false|string
    {
        return $this->record->getPartialData($offset, $length);
    }
}
