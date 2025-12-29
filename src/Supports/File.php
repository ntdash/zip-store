<?php

namespace ZipStore\Supports;

use Carbon\Carbon;
use Exception;
use SplFileInfo;
use Stringable;

class File implements Stringable
{
    private int $defaultTimestamp;

    private SplFileInfo $file;

    private string $packedCRC32Digest;

    /**
     * @return void
     *
     * @throws Exception
     */
    public function __construct(string $filepath)
    {
        $this->file = $this->validateFilepath($filepath);
    }

    public function __serialize()
    {
        $data = [
            'file' => $this->file->getRealPath(),
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
        return $this->getRealpath();
    }

    /**
     * @param  array{file:string,defaultTimestamp:int,packedCRC32Digest:string}  $data
     * @return void
     */
    public function __unserialize(array $data)
    {
        $this->file = $this->validateFilepath($data['file']);

        foreach (['defaultTimestamp', 'packedCRC32Digest'] as $key) {
            if (in_array($key, $data)) {
                $this->{$key} = $data[$key];
            }
        }
    }

    /** @return ($timestamp is true ? int : Carbon) */
    public function getATime(bool $timestamp = false): int|Carbon
    {
        $time = $this->file->getATime() ?: $this->getDefaultTimestamp();

        if ($timestamp) {
            return $time;
        }

        return Carbon::createFromTimestampUTC($time);
    }

    /** @return ($timestamp is true ? int : Carbon) */
    public function getCTime(bool $timestamp = false): int|Carbon
    {
        $time = $this->file->getCTime() ?: $this->getDefaultTimestamp();

        if ($timestamp) {
            return $time;
        }

        return Carbon::createFromTimestampUTC($time);
    }

    public function getExtension(): string
    {
        return $this->file->getExtension();
    }

    public function getFilename(): string
    {
        return $this->file->getFilename();
    }

    public function getFilepath(): string
    {
        return $this->file->getRealPath() ?: $this->file->getPathname();
    }

    public function getGID(): int
    {
        return $this->file->getGroup() ?: 1000;
    }

    /** @return ($timestamp is true ? int : Carbon) */
    public function getMTime(bool $timestamp = false): int|Carbon
    {
        $time = $this->file->getMTime() ?: $this->getDefaultTimestamp();

        if ($timestamp) {
            return $time;
        }

        return Carbon::createFromTimestampUTC($time);
    }

    public function getMode(): int
    {
        return $this->file->getPerms() ?: 0100644;
    }

    public function getPackedCRC32Digest(): string
    {
        if (! isset($this->packedCRC32Digest)) {
            $digest = hash_file('crc32b', $filepath = $this->getFilepath());

            if (false === $digest) {
                throw new Exception(sprintf('Failed to generate a crc-32 digest of file: %s', $filepath));
            }

            $this->packedCRC32Digest = pack('V', hexdec($digest));
        }

        return $this->packedCRC32Digest;
    }

    public function getRealpath(): string
    {
        /* @var string */
        return $this->file->getRealPath();
    }

    public function getSize(): int
    {
        return $this->file->getSize() ?: 0;
    }

    public function getUID(): int
    {
        return $this->file->getOwner() ?: 1000;
    }

    private function getDefaultTimestamp(): int
    {
        return $this->defaultTimestamp ??= \time();
    }

    private function validateFilepath(string $filepath): SplFileInfo
    {
        $info = new SplFileInfo($filepath);

        if (! $info->isFile() || ! $info->getRealPath()) {
            throw new Exception(sprintf('Failed to open %s: not a file', $filepath));
        }

        return $info;
    }
}
