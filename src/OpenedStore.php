<?php

namespace ZipStore;

use ZipStore\Exceptions\FileTooLargeException;
use ZipStore\Exceptions\ZipStoreIOException;
use ZipStore\Supports\StringBuffer;

class OpenedStore
{
    /** buffer size: 512 KiB */
    private const DEFAULT_BUFFER_SIZE = 0x8_0000;

    /** max size: 4 GiB */
    private const MAX_ZIP_FILESIZE = 0x1_0000_0000;

    public CentralDirectory $cdir;

    public readonly EntryCollection $entries;

    public EndOfCentralDirectory $eocdir;

    private int $readBytes;

    private int $size;

    /**
     * @param  list<EntryArgument>  $entries
     * @return void
     */
    public function __construct(array $entries)
    {
        $this->readBytes = 0;
        $this->entries = new EntryCollection($entries);

        $this->cdir = new CentralDirectory(
            $this,
            $this->entries->getEOFOffset()
        );

        $this->eocdir = new EndOfCentralDirectory($this->cdir);

        /** validate the effective zipfile upon initialization */
        $this->validateFilesize();

    }

    public function eof(): bool
    {
        return $this->getSize() === $this->readBytes;
    }

    public function getSize(): int
    {
        if (isset($this->size)) {
            return $this->size;
        }

        $entriesSize = $this->entries->getSize();
        $cdSize = $this->cdir->getSize();
        $eocdSize = $this->eocdir->getSize();

        return $this->size ??= $entriesSize + $cdSize + $eocdSize;
    }

    /**
     * Read a chunk of the virtual ZIP starting at the current read position or a provided offset.
     *
     * Reads up to `$length` bytes from the virtual ZIP stream, aggregating data from entries,
     * the central directory, and the end-of-central-directory as needed.
     *
     * @param int $length Number of bytes to read (absolute value is used).
     * @param int|null $offset If provided, seeks to this absolute offset before reading.
     * @param bool $throw If true, throw on underlying entry read failures; otherwise return `false`.
     * @return StringBuffer|false `StringBuffer` containing up to `$length` bytes, `false` on read failure when `$throw` is `false`.
     * @throws ZipStoreIOException When `$throw` is `true` and an underlying entry cannot be read.
     */
    public function read(int $length = self::DEFAULT_BUFFER_SIZE, ?int $offset = null, bool $throw = false): false|StringBuffer
    {
        $buffer = new StringBuffer((int) abs($length));

        if (null !== $offset) {
            $this->seek($offset);
        }

        $offset = $this->readBytes;

        /* fetch bytes */
        /* from entries if $offset < $entriesSize */
        if ($this->readBytes < $this->entries->getSize()) {

            foreach ($this->entries as $entry) {
                /* skip if not in range */
                if ($offset < $entry->offset || $offset >= $entry->offset + $entry->getSize()) {
                    continue;
                }

                $entryBuff = $entry->read(self::DEFAULT_BUFFER_SIZE, $offset);

                if (false === $entryBuff) {
                    /* TODO: report more information like entry and related file path */
                    if ($throw) {
                        throw new ZipStoreIOException(\sprintf('Failed to read zip-store from offset: %d', $offset));
                    } else {
                        return false;
                    }
                }

                $offset += $buffer->write($entryBuff);

                if ($buffer->isFull()) {
                    break;
                }
            }

        }

        /* and from central directory content if space left */
        if (($leftSize = $buffer->leftSize()) > 0) {
            $cdOffset = $this->cdir->getOffset();

            $offset += $buffer->write(
                $this->cdir->getContentChunk(
                    $leftSize,
                    $offset - $cdOffset
                )
            );
        }

        /* and, then from end of central directory if space still left */
        if (($leftSize = $buffer->leftSize()) > 0) {
            // @phpstan-ignore variable.undefined
            $eocdOffset = $cdOffset + $this->cdir->getSize();

            $offset += $buffer->write(
                $this->eocdir->getContentChunk(
                    $leftSize,
                    $offset - $eocdOffset
                )
            );
        }

        /* adjust offset */
        $this->readBytes += $buffer->size;

        return $buffer;
    }

    /**
     * seek virtually packed zip file at offset
     */
    public function seek(int $offset, int $whence = SEEK_SET): int
    {
        $offset += match ($whence) {
            SEEK_CUR => $this->readBytes,
            SEEK_END => $this->getSize(),
            default => 0,
        };

        if ($offset < 0) {
            return -1;
        }

        if ($offset > $this->getSize()) {
            $offset = $this->getSize();
        }

        $this->readBytes = $offset;

        return 0;
    }

    private function validateFilesize(): void
    {
        if (self::MAX_ZIP_FILESIZE < $this->getSize()) {
            throw new FileTooLargeException;
        }
    }
}
