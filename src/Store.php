<?php

namespace ZipStore;

use ZipStore\Exceptions\DuplicateEntryException;
use ZipStore\Exceptions\EntriesOverflowException;
use ZipStore\Exceptions\FileNotFoundException;
use ZipStore\Exceptions\FileTooLargeException;
use ZipStore\Exceptions\InvalidEntryNameException;
use ZipStore\Exceptions\ZipStoreException;

class Store
{
    /** attempt to resolve duplicates by appending numerical suffix to the resolved entryName */
    public const DUP_APPEND_NUM = 0x01;

    /** throw an exception when duplicate found but need the STRICT flag to be forwarded*/
    public const DUP_FAILED = 0x04;

    /** overwrite previous entry under the same resolved entryName */
    public const DUP_OVERWRITE = 0x02;

    /**  max entries count: 65535 */
    public const ENTRIES_LIMIT = 0xFFFF;

    /** max size: 3.75 GiB */
    public const ENTRY_MAX_FILESIZE = 0xF000_0000;

    /** placehold, default, ... */
    public const NO_EXTRA = 0x00;

    /** throw an exception entry (i.e: identifier and/or entryName) of one the adding method is invalid */
    public const STRICT = 0x80;

    private int $dupMode;

    /** @var array<string,EntryArgument> */
    private array $entries;

    private bool $strict;

    /**
     * Initialize a new Store and configure behavior according to the provided options.
     *
     * @param int $options Bitmask of option flags that control duplicate-resolution strategy and strict mode. Use class constants such as NO_EXTRA, STRICT, DUP_APPEND_NUM, DUP_OVERWRITE, and DUP_FAILED. Defaults to NO_EXTRA.
     */
    public function __construct(int $options = self::NO_EXTRA)
    {
        $this->entries = [];

        $this->parseOptions($options);
    }

    /**
         * Add a single file entry to the store.
         *
         * @param string|EntryArgument $identifierOrEntry A filesystem path to the file or an EntryArgument instance describing the entry.
         * @param string|null $entryName Optional archive entry name to use when the first argument is a path.
         * @return bool `true` if the entry was accepted, `false` if the addition failed (when not in strict mode).
         * @throws FileNotFoundException If a referenced file does not exist.
         * @throws InvalidEntryNameException If the provided entry name is invalid.
         */
    public function addFile(string|EntryArgument $identifierOrEntry, ?string $entryName = null): bool
    {
        if (is_string($identifierOrEntry)) {
            $identifierOrEntry = new EntryArgument($identifierOrEntry, $entryName);
        }

        return $this->addFiles([$identifierOrEntry]);
    }

    /**
     * @param  array<string|EntryArgument>  $entries
     *
     * @throws FileNotFoundException
     * @throws InvalidEntryNameException
     * @throws DuplicateEntryException
     */
    public function addFiles(array $entries): bool
    {
        foreach ($entries as $entry) {
            try {
                if (\is_string($entry)) {
                    $entry = new EntryArgument($entry);
                }

                $this->handleFileAddition($entry);

            } catch (ZipStoreException $th) {
                if ($this->strict) {
                    throw $th;
                }

                return false;
            }
        }

        return true;
    }

    /**
     * @return array<EntryArgument>
     */
    public function getEntries(): array
    {
        return \array_values($this->entries);
    }

    public function open(): OpenedStore
    {
        return new OpenedStore(\array_values($this->entries));
    }

    private function handleFileAddition(EntryArgument $entry): void
    {
        if (\count($this->entries) > self::ENTRIES_LIMIT) {
            throw new EntriesOverflowException;
        }

        $entry->validate();

        $this->validateEntryFileSize($entry);

        /** @var null|EntryArgument */
        $slot = &$this->entries[$entry->entryName];

        if (null === $slot) {
            $slot = $entry;

            return;
        }

        $resolvedDup = $this->resolveDuplicatedEntry($slot, $entry);

        $this->entries[$resolvedDup->entryName] = $resolvedDup;
    }

    private function parseOptions(int $options): void
    {
        $this->strict = (bool) ($options & self::STRICT);

        $this->dupMode = $options & ~self::STRICT;

        if (0 === $this->dupMode && $this->strict) {
            $this->dupMode |= self::DUP_FAILED;
        }

    }

    /**
     * Resolve a name collision between an existing entry and a new entry according to the store's duplication mode.
     *
     * @param EntryArgument $current The existing entry that conflicts with the incoming entry's name.
     * @param EntryArgument $newValue The incoming entry proposed for addition.
     * @return EntryArgument The entry that should be stored (may be the incoming entry, a renamed clone, or the existing entry replaced).
     * @throws DuplicateEntryException If the duplication mode is set to fail on duplicates.
     */
    private function resolveDuplicatedEntry(EntryArgument $current, EntryArgument $newValue): EntryArgument
    {

        switch (true) {
            case $this->dupMode & self::DUP_APPEND_NUM:

                $count = 0;
                $len = \strlen($current->entryName);

                foreach ($this->entries as $entry) {
                    if (0 == \strncmp($current->entryName, $entry->entryName, $len)) {
                        $count++;
                    }
                }

                $current = $newValue->clone(entryName: \sprintf(
                    '%s_%s',
                    $newValue->entryName,
                    \str_pad((string) ($count + 1), 2, '0', STR_PAD_LEFT)
                ));
                break;
            case $this->dupMode & self::DUP_OVERWRITE:
                $current = $newValue;
                break;
            case $this->dupMode & self::DUP_FAILED:
                throw new DuplicateEntryException;
        }

        return $current;
    }

    /**
     * Ensures the entry's file size does not exceed the store's maximum allowed entry size.
     *
     * @param EntryArgument $entry The entry whose file size will be validated.
     * @throws FileTooLargeException If the entry's file size is greater than self::ENTRY_MAX_FILESIZE.
     */
    private function validateEntryFileSize(EntryArgument $entry): void
    {
        if (self::ENTRY_MAX_FILESIZE < $entry->file->getSize()) {
            throw new FileTooLargeException;
        }
    }
}
