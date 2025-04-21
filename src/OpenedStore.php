<?php

namespace ZipStore;

use OutOfRangeException;
use ZipStore\Exceptions\OutOfRangeOffsetException;
use ZipStore\Exceptions\ZipStoreIOException;
use ZipStore\Supports\StringBuffer;

/**
 * @phpstan-import-type NormalizedEntryDetails from Store
 */
class OpenedStore
{
    private const DEFAULT_BUFFER_SIZE = 1024 * 512;

    public CentralDirectory $cdir;

    public readonly EntryCollection $entries;

    public EndOfCentralDirectory $eocdir;

    private int $readingOffset;

    private int $size;

    /**
     * @param  list<NormalizedEntryDetails>  $entries
     * @return void
     */
    public function __construct(array $entries)
    {
        $this->readingOffset = 0;
        $this->entries = new EntryCollection($entries);

        $this->cdir = new CentralDirectory(
            $this,
            $this->entries->getEOFOffset()
        );

        $this->eocdir = new EndOfCentralDirectory($this->cdir);
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
     * read $bytes of the virtually packed zip file
     * from the @seek(ed) offset
     *
     * @return ($throw is true ? StringBuffer : false|StringBuffer)
     */
    public function read(?int $bytes = null, ?int $offset = null, bool $throw = false): false|StringBuffer
    {
        $bytes ??= self::DEFAULT_BUFFER_SIZE;

        $buffer = new StringBuffer((int) abs($bytes));

        if (null !== $offset) {
            $this->seek($offset);
        }

        $offset = $this->readingOffset;

        /* fetch bytes */
        /* from entries if $offset < $entriesSize */
        if ($this->readingOffset < $this->entries->getSize()) {

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
        $this->readingOffset += $buffer->size;

        return $buffer;
    }

    /**
     * seek virtually packed zip file at offset
     */
    public function seek(int $offset): void
    {
        if ($offset < 0) {
            throw new OutOfRangeException('Negative offset not supported');
        }

        if ($offset > $this->getSize()) {
            throw new OutOfRangeOffsetException(sprintf(
                'Attempt to seeked out of range offset (%s) from a file with size: %d',
                $offset,
                $this->size
            ));
        }

        $this->readingOffset = $offset;
    }
}
