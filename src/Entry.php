<?php

namespace ZipStore;

use ZipStore\Contracts\ZipStoreEntryFile;
use ZipStore\Supports\StringBuffer;

class Entry
{
    public readonly string $entryName;

    public readonly LocalHeader $localHeader;

    private int $size;

    public function __construct(public readonly int $offset, public readonly ZipStoreEntryFile $file, ?string $entryName = null)
    {

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

            $read = $this->file->read($localOffset, $leftSize);

            if (false === $read) {
                return false;
            }

            $buffer->write($read);
        }

        return $buffer;
    }
}
