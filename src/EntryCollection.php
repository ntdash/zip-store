<?php

namespace ZipStore;

use ArrayIterator;
use Countable;
use IteratorAggregate;
use Traversable;

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

    /**
     * Compute the total size of all entries in the collection.
     *
     * @return int Total size of every Entry in the collection; cached after the first computation.
     */
    public function getSize(): int
    {
        return $this->size ??= array_reduce(
            array: $this->entries,
            initial: 0,
            callback: fn ($result, $entry) => $result + $entry->getSize()
        );
    }

    /**
     * Create an Entry for the given argument positioned at the collection's current EOF offset.
     *
     * @param EntryArgument $arg Source descriptor containing the file and entry name.
     * @return Entry The newly constructed Entry with its offset set to the collection's EOF. 
     */
    private function createEntry(EntryArgument $arg): Entry
    {
        return new Entry(
            $this->getEOFOffset(),
            $arg->file,
            $arg->entryName
        );
    }
}
