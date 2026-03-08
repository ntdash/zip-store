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
     * Create a LocalFile representing the given filesystem path.
     *
     * @param string $filepath Path to the target file.
     * @throws FileNotFoundException If the path does not point to an existing file or has no real path.
     */
    public function __construct(string $filepath)
    {
        $this->fileinfo = $this->validateFilepath($filepath);
    }

    /**
     * Produce an array representation of the object's serializable state.
     *
     * The returned array always contains:
     * - `filepath`: the file's real path.
     *
     * It may also contain:
     * - `defaultTimestamp`: cached default timestamp (int) when set.
     * - `packedCRC32Digest`: cached packed CRC-32 digest (binary string) when set.
     *
     * @return array<string, mixed> Serializable state for __unserialize().
     */
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

    /**
     * Get the file's identifier as a string.
     *
     * @return string The file identifier: the real path if available, otherwise the pathname.
     */
    public function __toString(): string
    {
        return $this->getIdentifier();
    }

    /**
     * Restore the object's internal state from a serialized array.
     *
     * Recreates the SplFileInfo for the supplied 'filepath' and restores optional
     * cached properties 'defaultTimestamp' and 'packedCRC32Digest' if present.
     *
     * @param array{filepath:string,defaultTimestamp?:int,packedCRC32Digest?:string} $data Serialized state produced by __serialize().
     * @throws FileNotFoundException If the provided filepath does not point to a valid file.
     */
    public function __unserialize(array $data)
    {
        $this->fileinfo = $this->validateFilepath($data['filepath']);

        foreach (['defaultTimestamp', 'packedCRC32Digest'] as $key) {
            if (in_array($key, $data)) {
                $this->{$key} = $data[$key];
            }
        }
    }

    / **
     * Checks whether the underlying path refers to an existing regular file.
     *
     * @return bool `true` if the underlying path is an existing regular file, `false` otherwise.
     */
    public function exists(): bool
    {
        return $this->fileinfo->isFile();
    }

    /**
     * Get the file's access time.
     *
     * @param bool $timestamp If true, return the Unix timestamp; otherwise return a Carbon instance in UTC.
     * @return int|Carbon The access time as an `int` when `$timestamp` is true, or a `Carbon` (UTC) instance otherwise.
     */
    public function getATime(bool $timestamp = false): int|Carbon
    {
        $time = $this->fileinfo->getATime() ?: $this->getDefaultTimestamp();

        if ($timestamp) {
            return $time;
        }

        return Carbon::createFromTimestampUTC($time);
    }

    /**
     * Get the file's inode change time (ctime) as a timestamp or a Carbon UTC instance.
     *
     * @param bool $timestamp If true, return the raw UNIX timestamp; otherwise return a Carbon instance in UTC.
     * @return int|Carbon `int` when `$timestamp` is true, `Carbon` representing the ctime in UTC otherwise.
     */
    public function getCTime(bool $timestamp = false): int|Carbon
    {
        $time = $this->fileinfo->getCTime() ?: $this->getDefaultTimestamp();

        if ($timestamp) {
            return $time;
        }

        return Carbon::createFromTimestampUTC($time);
    }

    /**
     * Get the file extension of the underlying file.
     *
     * @return string The file extension (without leading dot), or an empty string if the file has no extension.
     */
    public function getExtension(): string
    {
        return $this->fileinfo->getExtension();
    }

    /**
     * Get the base name of the file (filename including its extension).
     *
     * @return string The file's base name.
     */
    public function getFilename(): string
    {
        return $this->fileinfo->getFilename();
    }

    /**
     * Return the file's group ID.
     *
     * @return int The file's group ID, or 1000 if the group ID is not available.
     */
    public function getGID(): int
    {
        return $this->fileinfo->getGroup() ?: 1000;
    }

    /**
     * Provide the canonical identifier for the underlying file.
     *
     * @return string The file's real path if available, otherwise the file's pathname.
     */
    public function getIdentifier(): string
    {
        /* @var string */
        return $this->fileinfo->getRealPath() ?: $this->fileinfo->getPathname();
    }

    /**
     * Get the file's modification time.
     *
     * @param bool $timestamp When true, return the raw Unix timestamp; when false, return a Carbon instance.
     * @return int|Carbon If `$timestamp` is true, the Unix timestamp (seconds since the Unix epoch); otherwise a `Carbon` instance in UTC representing the modification time.
     */
    public function getMTime(bool $timestamp = false): int|Carbon
    {
        $time = $this->fileinfo->getMTime() ?: $this->getDefaultTimestamp();

        if ($timestamp) {
            return $time;
        }

        return Carbon::createFromTimestampUTC($time);
    }

    /**
     * Retrieve the file permission mode.
     *
     * @return int The file's permission mode as an integer; returns 0100644 if permissions are unavailable.
     */
    public function getMode(): int
    {
        return $this->fileinfo->getPerms() ?: 0100644;
    }

    /**
     * Return the file's CRC-32 digest as a 4-byte little-endian binary string.
     *
     * The digest is computed from the file identified by getIdentifier() and cached for subsequent calls.
     *
     * @return string Packed 4-byte little-endian binary CRC-32 digest.
     * @throws Exception If the CRC-32 digest cannot be generated for the file.
     */
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

    /**
     * Return the size of the underlying file.
     *
     * @return int File size in bytes, or 0 if unavailable.
     */
    public function getSize(): int
    {
        return $this->fileinfo->getSize() ?: 0;
    }

    /**
     * Retrieve the file owner's user ID.
     *
     * @return int The owner's user ID, or 1000 if the owner cannot be determined.
     */
    public function getUID(): int
    {
        return $this->fileinfo->getOwner() ?: 1000;
    }

    /**
     * Reads a portion of the file starting at a byte offset.
     *
     * @param int $offset The zero-based byte offset to start reading from.
     * @param int $length The maximum number of bytes to read.
     * @return false|string The read bytes as a string, or `false` on failure.
     */
    public function read(int $offset, int $length): false|string
    {
        // @phpstan-ignore argument.type
        return \file_get_contents($this->getIdentifier(), offset: $offset, length: $length);
    }

    /**
     * Get the cached default timestamp, initializing it to the current time if unset.
     *
     * @return int The cached UNIX timestamp (seconds since the Unix epoch).
     */
    private function getDefaultTimestamp(): int
    {
        return $this->defaultTimestamp ??= \time();
    }

    /**
     * Validate that the given path points to an existing regular file and return its SplFileInfo.
     *
     * @param string $filepath Path to validate.
     * @return SplFileInfo The SplFileInfo for the validated file.
     * @throws FileNotFoundException If the path does not resolve to an existing regular file.
     */
    private function validateFilepath(string $filepath): SplFileInfo
    {
        $info = new SplFileInfo($filepath);

        if (! $info->isFile() || ! $info->getRealPath()) {
            throw new FileNotFoundException(sprintf('Failed to open %s: not a file', $filepath));
        }

        return $info;
    }
}
