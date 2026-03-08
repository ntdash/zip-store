<?php

namespace ZipStore\Contracts;

use Carbon\Carbon;
use Stringable;

interface ZipStoreEntryFile extends Stringable
{
    /**
 * Determine whether the entry exists.
 *
 * @return bool `true` if the entry exists, `false` otherwise.
 */
public function exists(): bool;

    /**
 * Get the entry's last access time.
 *
 * @param bool $timestamp If true, return the Unix timestamp.
 * @return int|Carbon `int` when `$timestamp` is true, otherwise a `Carbon` instance representing the access time.
 */
    public function getATime(bool $timestamp = false): int|Carbon;

    /** @return ($timestamp is true ? int : Carbon) */
    public function getCTime(bool $timestamp = false): int|Carbon;

    public function getExtension(): string;

    /**
 * Get the entry's filename.
 *
 * @return string The filename component of the entry (without directory path).
 */
public function getFilename(): string;

    /**
 * Retrieve the numeric group identifier that owns the entry.
 *
 * @return int The group ID of the entry.
 */
public function getGID(): int;

    /**
 * Retrieves a stable, unique identifier for this entry within the zip store.
 *
 * @return string A unique identifier string for the entry.
 */
public function getIdentifier(): string;

    /**
 * Retrieve the entry's modification time.
 *
 * @param bool $timestamp If true, return the UNIX timestamp as an `int`; otherwise return a `Carbon` instance.
 * @return int|Carbon `int` when $timestamp is true, `Carbon` otherwise.
 */
    public function getMTime(bool $timestamp = false): int|Carbon;

    /**
 * Get the file mode for the entry (permission and file type bits).
 *
 * @return int The file mode bitmask representing permissions and type. 
 */
public function getMode(): int;

    /**
 * Get the packed CRC32 digest of the entry.
 *
 * @return string The 4-byte binary (packed) CRC32 digest for the entry.
 */
public function getPackedCRC32Digest(): string;

    /**
 * Retrieve the size of the entry in bytes.
 *
 * @return int The size of the entry in bytes.
 */
public function getSize(): int;

    /**
 * Retrieve the owner user identifier of the entry.
 *
 * @return int The user ID of the entry's owner.
 */
public function getUID(): int;

    public function read(int $offset, int $length): false|string;
}
