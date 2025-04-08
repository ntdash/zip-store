<?php

namespace ZipStore;

use ZipStore\Supports\File;
use ZipStore\Supports\StringBuffer;

class Entry
{
    public readonly string $entryName;

    public readonly File $file;

    public readonly LocalHeader $localHeader;

    private int $size;

    public function __construct(public readonly int $offset, string $filepath, ?string $entryName = null)
    {
        $this->file = new File($filepath);
        $this->entryName = $entryName ?? $this->file->getFilename();

        $this->localHeader = new LocalHeader($this);
    }

    public function getSize(): int
    {
        return $this->size ??= $this->file->getSize() + $this->localHeader->getSize();
    }

    public function read(int $bytes, int $absOffset): false|StringBuffer
    {
        if (($localOffset = $absOffset - $this->offset) < 0) {
            return false;
        }

        $buffer = new StringBuffer($bytes);

        /* read from header */
        if ($localOffset < $this->localHeader->getSize()) {
            $read = \substr($this->localHeader->getContent(), $localOffset);
            $localOffset += $buffer->write($read);
        }

        /* then from file if space left */
        if (($leftSize = $buffer->leftSize()) > 0) {
            /* re-adjust local offset */
            $localOffset -= $this->localHeader->getSize();

            $read = \file_get_contents($this->file->getRealpath(), offset: $localOffset, length: $leftSize);

            if (false === $read) {
                return false;
            }

            $buffer->write($read);
        }

        return $buffer;
    }
}
