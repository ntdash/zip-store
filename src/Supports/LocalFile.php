<?php

namespace ZipStore\Supports;

use Carbon\Carbon;
use Exception;
use SplFileInfo;
use ZipStore\Contracts\ZipStoreEntryFile;
use ZipStore\Exceptions\FileNotFoundException;

class LocalFile implements ZipStoreEntryFile
{
    private int $defaultTimestamp;

    private SplFileInfo $fileinfo;

    private string $packedCRC32Digest;

    /**
     * @return void
     *
     * @throws Exception
     */
    public function __construct(string $filepath)
    {
        $this->fileinfo = $this->validateFilepath($filepath);
    }

    public function __serialize()
    {
        $data = [
            'filepath' => $this->fileinfo->getRealPath(),
        ];

        if (isset($this->defaultTimestamp)) {
            $data['defaultTimestamp'] = $this->defaultTimestamp;
        }

        if (isset($this->packedCRC32Digest)) {
            $data['packedCRC32Digest'] = $this->packedCRC32Digest;
        }

        return $data;
    }

    public function __toString(): string
    {
        return $this->getIdentifier();
    }

    /**
     * @param  array{filepath:string,defaultTimestamp:int,packedCRC32Digest:string}  $data
     * */
    public function __unserialize(array $data)
    {
        $this->fileinfo = $this->validateFilepath($data['filepath']);

        foreach (['defaultTimestamp', 'packedCRC32Digest'] as $key) {
            if (in_array($key, $data)) {
                $this->{$key} = $data[$key];
            }
        }
    }

    public function exists(): bool
    {
        return $this->fileinfo->isFile();
    }

    /** @return ($timestamp is true ? int : Carbon) */
    public function getATime(bool $timestamp = false): int|Carbon
    {
        $time = $this->fileinfo->getATime() ?: $this->getDefaultTimestamp();

        if ($timestamp) {
            return $time;
        }

        return Carbon::createFromTimestampUTC($time);
    }

    /** @return ($timestamp is true ? int : Carbon) */
    public function getCTime(bool $timestamp = false): int|Carbon
    {
        $time = $this->fileinfo->getCTime() ?: $this->getDefaultTimestamp();

        if ($timestamp) {
            return $time;
        }

        return Carbon::createFromTimestampUTC($time);
    }

    public function getExtension(): string
    {
        return $this->fileinfo->getExtension();
    }

    public function getFilename(): string
    {
        return $this->fileinfo->getFilename();
    }

    public function getGID(): int
    {
        return $this->fileinfo->getGroup() ?: 1000;
    }

    public function getIdentifier(): string
    {
        /* @var string */
        return $this->fileinfo->getRealPath() ?: $this->fileinfo->getPathname();
    }

    /** @return ($timestamp is true ? int : Carbon) */
    public function getMTime(bool $timestamp = false): int|Carbon
    {
        $time = $this->fileinfo->getMTime() ?: $this->getDefaultTimestamp();

        if ($timestamp) {
            return $time;
        }

        return Carbon::createFromTimestampUTC($time);
    }

    public function getMode(): int
    {
        return $this->fileinfo->getPerms() ?: 0100644;
    }

    public function getPackedCRC32Digest(): string
    {
        if (! isset($this->packedCRC32Digest)) {
            $digest = hash_file('crc32b', $filepath = $this->getIdentifier());

            if (false === $digest) {
                throw new Exception(sprintf('Failed to generate a crc-32 digest of file: %s', $filepath));
            }

            $this->packedCRC32Digest = pack('V', hexdec($digest));
        }

        return $this->packedCRC32Digest;
    }

    public function getSize(): int
    {
        return $this->fileinfo->getSize() ?: 0;
    }

    public function getUID(): int
    {
        return $this->fileinfo->getOwner() ?: 1000;
    }

    public function read(int $offset, int $length): false|string
    {
        // @phpstan-ignore argument.type
        return \file_get_contents($this->getIdentifier(), offset: $offset, length: $length);
    }

    private function getDefaultTimestamp(): int
    {
        return $this->defaultTimestamp ??= \time();
    }

    private function validateFilepath(string $filepath): SplFileInfo
    {
        $info = new SplFileInfo($filepath);

        if (! $info->isFile() || ! $info->getRealPath()) {
            throw new FileNotFoundException(sprintf('Failed to open %s: not a file', $filepath));
        }

        return $info;
    }
}
