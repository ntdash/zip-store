<?php

namespace ZipStore\Contracts;

use ZipStore\Supports\StringBuffer;

interface ZipStoreFile
{
    public function getCRC32(): string;

    public function getSize(): int;

    public function read(): false|StringBuffer;
}
