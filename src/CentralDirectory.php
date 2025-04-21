<?php

namespace ZipStore;

use ZipStore\Supports\StringBuffer;

class CentralDirectory
{
    /* caching */
    private string $content;

    private int $size;

    /**
     * @return void
     */
    public function __construct(
        private OpenedStore $store,
        private readonly int $offset
    ) {}

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

    public function getEntriesCount(): int
    {
        return $this->store->entries->count();
    }

    public function getOffset(): int
    {
        return $this->offset;
    }

    public function getSize(): int
    {
        return $this->size ??= \strlen($this->getContent());
    }

    /**
     * Generate Central directory Entries
     */
    private function generate(): string
    {
        $content = '';

        foreach ($this->store->entries as $entry) {
            $content .= (new CentralDirectoryEntry($entry))->getContent();
        }

        return $content;
    }
}
