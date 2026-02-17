<?php

namespace ZipStore;

use ZipStore\Contracts\ZipStoreFile;
use ZipStore\Exceptions\BadEntryFileAbstract;
use ZipStore\Supports\File;
use ZipStore\Supports\StringBuffer;

class Entry
{
    /** @var class-string<ZipStoreFile> */
    private static $entryFileClass = File::class;

    public readonly string $entryName;

    public readonly ZipStoreFile $file;

    public readonly LocalHeader $localHeader;

    private int $size;

    public function __construct(public readonly int $offset, string $filepath, ?string $entryName = null)
    {
        $this->file = new self::$entryFileClass($filepath);

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

            // $read = \file_get_contents($this->file->getRealpath(), offset: $localOffset, length: $leftSize);
            $read = $this->file->read($localOffset, $leftSize);

            if (false === $read) {
                return false;
            }

            $buffer->write($read);
        }

        return $buffer;
    }

    /**
     * @param  class-string  $abstract
     * @return void
     */
    public static function setEntryFileClass(string $abstract)
    {
        if (! \is_a($abstract, ZipStoreFile::class, true)) {
            throw new BadEntryFileAbstract(
                \sprintf('"%s" does not implement the "%s" interface', $abstract,ZipStoreFile::class)
            );
        }

        foreach (['__serialize', '__unserialize'] as $s_method) {
            if (! \method_exists($abstract, $s_method)) {
                throw new BadEntryFileAbstract('Missing serialization magic methods');
            }
        }

        self::$entryFileClass = $abstract;
    }
}
