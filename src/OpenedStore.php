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

    private int $offset;

    private int $size;

    /**
     * @param  list<EntryArgument>  $entries
     * @return void
     */
    public function __construct(array $entries)
    {
        $this->offset = 0;
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
        return $this->getSize() === $this->offset;
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

    public function passthru(): void
    {
        $this->writeTo('php://output', true);
    }

    /**
     * read $bytes of the virtually packed zip file
     * from the @seek(ed) offset
     *
     * @return ($throw is true ? StringBuffer : false|StringBuffer)
     */
    public function read(int $length = self::DEFAULT_BUFFER_SIZE, ?int $offset = null, bool $throw = false): false|StringBuffer
    {
        $buffer = new StringBuffer((int) abs($length));

        if (null !== $offset) {
            $this->seek($offset);
        }

        $offset = $this->offset;

        /* fetch bytes */
        /* from entries if $offset < $entriesSize */
        if ($this->offset < $this->entries->getSize()) {

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
        $this->offset += $buffer->size;

        return $buffer;
    }

    /**
     * seek virtually packed zip file at offset
     */
    public function seek(int $offset, int $whence = SEEK_SET): int
    {
        $offset += match ($whence) {
            SEEK_CUR => $this->offset,
            SEEK_END => $this->getSize(),
            default => 0,
        };

        if ($offset < 0) {
            return -1;
        }

        if ($offset > $this->getSize()) {
            $offset = $this->getSize();
        }

        $this->offset = $offset;

        return 0;
    }

    public function tell(): int|false
    {
        return $this->offset;
    }

    public function writeTo(string $path, bool $resetOffset = true): void
    {
        $stream = \fopen($path, 'w');

        if (! $stream) {
            throw new \Exception("Failed to open {$path}");
        }

        try {
            $this->writeToStream($stream, $resetOffset);
        } finally {
            \fclose($stream);
        }
    }

    /**
     * @param  resource  $stream
     * @return void
     */
    public function writeToStream(mixed $stream, bool $resetOffset = false)
    {
        if ($resetOffset) {
            $this->seek(0);
        }

        while (! $this->eof()) {
            $buffer = $this->read(throw: true);
            $written = \fwrite($stream, $buffer, $buffer->size);

            if ($written !== $buffer->size) {
                throw new \Exception('Failed to write buffer into stream');
            }
        }

        \fflush($stream);
    }

    private function validateFilesize(): void
    {
        if (self::MAX_ZIP_FILESIZE < $this->getSize()) {
            throw new FileTooLargeException;
        }
    }
}
