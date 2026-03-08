<?php

namespace ZipStore\Contracts;

use Carbon\Carbon;
use Stringable;

interface ZipStoreEntryFile extends Stringable
{
    public function exists(): bool;

    /** @return ($timestamp is true ? int : Carbon) */
    public function getATime(bool $timestamp = false): int|Carbon;

    /** @return ($timestamp is true ? int : Carbon) */
    public function getCTime(bool $timestamp = false): int|Carbon;

    public function getExtension(): string;

    public function getFilename(): string;

    public function getGID(): int;

    public function getIdentifier(): string;

    /** @return ($timestamp is true ? int : Carbon) */
    public function getMTime(bool $timestamp = false): int|Carbon;

    public function getMode(): int;

    public function getPackedCRC32Digest(): string;

    public function getSize(): int;

    public function getUID(): int;

    public function read(int $offset, int $length): false|string;
}
