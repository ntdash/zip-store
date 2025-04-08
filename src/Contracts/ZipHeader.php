<?php

namespace ZipStore\Contracts;

interface ZipHeader
{
    public function getContent(): string;

    public function getSize(): int;
}
