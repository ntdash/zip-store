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
        return true;
    }

    public function getATime(bool $timestamp = false): int|Carbon
    {
        return $this->getMTime();
    }

    public function getCTime(bool $timestamp = false): int|Carbon
    {
        return Carbon::createFromTimestampUTC(intval($this->record->meta['created_at']));
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
        return posix_getgid();
    }

    public function getIdentifier(): string
    {
        return (string) $this->record->id;
    }

    public function getMTime(bool $timestamp = false): int|Carbon
    {
        return Carbon::createFromTimestampUTC(intval($this->record->meta['updated_at']));
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
        return posix_getuid();
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
