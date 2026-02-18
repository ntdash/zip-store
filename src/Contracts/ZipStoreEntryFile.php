<?php

namespace ZipStore\Contracts;

use Carbon\Carbon;
use Serializable;
use Stringable;

interface ZipStoreEntryFile extends Stringable
{
    /** @return ($timestamp is true ? int : Carbon) */
    public function getATime(bool $timestamp = false): int|Carbon;

    /** @return ($timestamp is true ? int : Carbon) */
    public function getCTime(bool $timestamp = false): int|Carbon;

    public function getExtension(): string;

    public function getFilename(): string;

    public function getFilepath(): string;

    public function getGID(): int;

    /** @return ($timestamp is true ? int : Carbon) */
    public function getMTime(bool $timestamp = false): int|Carbon;

    public function getMode(): int;

    public function getPackedCRC32Digest(): string;

    public function getRealpath(): string;

    public function getSize(): int;

    public function getUID(): int;

    public function read(int $offset, int $length): false|string;
}
