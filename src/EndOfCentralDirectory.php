<?php

namespace ZipStore;

use ZipStore\Contracts\ZipHeader;
use ZipStore\Supports\StringBuffer;

class EndOfCentralDirectory implements ZipHeader
{
    protected const SIGNATURE = 0x06054B50;

    /* caching */
    private string $content;

    private int $size;

    public function __construct(private CentralDirectory $centralDirectory) {}

    public function getContent(): string
    {
        return $this->content ??= $this->generate();
    }

    public function getContentChunk(int $length, int $offset = 0): StringBuffer
    {
        $buffer = new StringBuffer($length);

        $buffer->write(\substr($this->getContent(), $offset, $length));

        return $buffer;
    }

    public function getSize(): int
    {
        return $this->size ??= \strlen($this->getContent());
    }

    private function generate(): string
    {
        $b2null = pack('v', 0x00);

        $entriesCount = $this->centralDirectory->getEntriesCount();

        /* signature [4B] */
        $content = pack('V', self::SIGNATURE);

        /* number (as in #id) of this disk [2B] */
        $content .= $b2null;

        /* disk where central directory starts [2B] */
        $content .= $b2null;

        /* number of central directory records on this disk [2B] */
        $content .= pack('v', $entriesCount);

        /* total number of central directory records [2B] */
        $content .= pack('v', $entriesCount);

        /* size of the central directory [note: EOCD not included] */
        $content .= pack('V', $this->centralDirectory->getSize());

        /* offset of the start of central directory, relative to start of archive */
        $content .= pack('V', $this->centralDirectory->getOffset());

        /* comment length */
        $content .= $b2null;

        /* comment */
        $content .= '';

        return $content;
    }
}
