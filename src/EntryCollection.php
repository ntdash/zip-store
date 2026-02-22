<?php

namespace ZipStore;

use ArrayIterator;
use Countable;
use IteratorAggregate;
use Traversable;
use ZipStore\Supports\EntryArgument;

/**
 * @implements IteratorAggregate<int,Entry>
 */
class EntryCollection implements Countable, IteratorAggregate
{
    /**
     * @var list<Entry>
     */
    private array $entries;

    private int $size;

    /**
     * @param  list<EntryArgument>  $entries
     */
    public function __construct(array $entries)
    {
        $this->entries = [];

        foreach ($entries as $entry) {
            $this->entries[] = $this->createEntry($entry);
        }
    }

    public function count(): int
    {
        return \count($this->entries);
    }

    public function getEOFOffset(): int
    {
        /** @var null|Entry */
        $last = $this->entries[\count($this->entries) - 1] ?? null;

        return $last?->offset + $last?->getSize();
    }

    public function getIterator(): Traversable
    {
        return new ArrayIterator($this->entries);
    }

    public function getSize(): int
    {
        return $this->size ??= array_reduce(
            array: $this->entries,
            initial: 0,
            callback: fn ($result, $entry) => $result + $entry->getSize()
        );
    }


    private function createEntry(EntryArgument $args): Entry
    {
        return new Entry(
            $this->getEOFOffset(),
            $args->filepath,
            $args->entryName
        );
    }
}
