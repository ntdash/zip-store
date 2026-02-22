<?php

namespace ZipStore;

use ZipStore\Exceptions\DuplicateEntryException;
use ZipStore\Exceptions\EntriesOverflowException;
use ZipStore\Exceptions\FileTooLargeException;
use ZipStore\Exceptions\InvalidEntryNameException;
use ZipStore\Exceptions\InvalidFilepathException;
use ZipStore\Exceptions\ZipStoreException;
use ZipStore\Supports\EntryArgument;

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

    /** max size: 3.75GB */
    public const ENTRY_MAX_FILESIZE = 0xF000_0000;

    /** placehold, default, ... */
    public const NO_EXTRA = 0x00;

    /** throw an exception entry (i.e: filepath and/or entryName) of one the adding method is invalid */
    public const STRICT = 0x80;

    private int $dupMode;

    /** @var array<string,EntryArgument> */
    private array $entries;

    private bool $strict;

    public function __construct(int $options = self::NO_EXTRA)
    {
        $this->entries = [];

        $this->parseOptions($options);
    }

    /**
     * @throws InvalidFilepathException
     * @throws InvalidEntryNameException
     */
    public function addFile(string|EntryArgument $filepathOrEntry, ?string $entryName = null): bool
    {
        if (is_string($filepathOrEntry)) {
            $filepath = $filepathOrEntry;
            $entryName ??= \basename($filepath);

            $filepathOrEntry = new EntryArgument(\compact('entryName', 'filepath'));
        }

        return $this->addFiles([$filepathOrEntry]);
    }

    /**
     * @param  array<string|EntryArgument>  $entries
     *
     * @throws InvalidFilepathException
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

    private function resolveDuplicatedEntry(EntryArgument $current, EntryArgument $newValue): EntryArgument
    {

        switch (true) {
            case $this->dupMode & self::DUP_APPEND_NUM:

                $count = 0;
                $len = \strlen($current->entryName);

                foreach ($this->entries as $entry) {
                    if (0 == \strncmp($current->entryName, $entry->entryName, $len))
                        $count++;
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

    private function validateEntryFileSize(EntryArgument $entry): void
    {
        \clearstatcache(true, $entry->filepath);
        $entryFilesize = \filesize($entry->filepath);

        if (false === $entryFilesize || self::ENTRY_MAX_FILESIZE < $entryFilesize) {
            throw new FileTooLargeException;
        }
    }
}
