<?php

namespace ZipStore;

use ZipStore\Contracts\ZipStoreEntryFile;
use ZipStore\Supports\StringBuffer;

class Entry
{
    public readonly string $entryName;

    public readonly LocalHeader $localHeader;

    private int $size;

    /**
     * Create a new Entry representing a file within the ZIP at a given archive offset.
     *
     * The constructor records the entry's archive offset and associated entry file, sets the entry name
     * to the provided value or to the file's filename if omitted, and initializes the entry's local header.
     *
     * @param int $offset The byte offset of this entry's local header within the ZIP archive.
     * @param ZipStoreEntryFile $file The underlying entry file providing filename, size and read access.
     * @param string|null $entryName Optional explicit entry name; when null the file's filename is used.
     */
    public function __construct(public readonly int $offset, public readonly ZipStoreEntryFile $file, ?string $entryName = null)
    {

        $this->entryName = $entryName ?? $this->file->getFilename();

        $this->localHeader = new LocalHeader($this);
    }

    public function getSize(): int
    {
        return $this->size ??= $this->file->getSize() + $this->localHeader->getSize();
    }

    /**
     * Read up to a given number of bytes from this entry starting at an absolute archive offset.
     *
     * @param int $bytes Number of bytes to read.
     * @param int $absOffset Absolute offset within the archive from which to start reading.
     * @return false|StringBuffer `StringBuffer` containing the assembled bytes (header bytes followed by file bytes) on success, or `false` if the offset is outside the entry or the underlying file read fails.
     */
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

            // $read = \file_get_contents($this->file->getRealpath(), offset: $localOffset, length: $leftSize);
            $read = $this->file->read($localOffset, $leftSize);

            if (false === $read) {
                return false;
            }

            $buffer->write($read);
        }

        return $buffer;
    }
}
