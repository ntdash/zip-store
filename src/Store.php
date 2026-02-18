<?php

namespace ZipStore;

use ZipStore\Exceptions\InvalidEntryNameException;
use ZipStore\Exceptions\InvalidFilepathException;

/**
 * @phpstan-type NormalizedEntryDetails array{entryName:string,filepath:string}
 */
class Store
{
    /** @var array<string,NormalizedEntryDetails> */
    private array $entries;

    public function __construct()
    {
        $this->entries = [];
    }

    /**
     *
     * @param string $filepath
     * @param null|string $entryName
     * @param bool $strict
     *
     * @return bool
     *
     * @throws InvalidFilepathException
     * @throws InvalidEntryNameException
     */
    public function addFile(string $filepath, ?string $entryName = null, bool $strict = false): bool
    {
        $entryName ??= \basename($filepath);

        return $this->addFiles([\compact('entryName', 'filepath')]);
    }

    /**
     * @param  list<string|NormalizedEntryDetails>  $entries
     *
     * @return bool
     *
     * @throws InvalidFilepathException
     * @throws InvalidEntryNameException
     */
    public function addFiles(array $entries, bool $strict = false): bool
    {
        foreach ($entries as $entry) {

            $entry = $this->normalizeEntry($entry);

            if ($strict) {
                $this->validateEntry($entry);
            } else {
                if (!$this->validateEntryLoosely($entry)) {
                    return false;
                }
            }

            /** @var null|string */
            $resolved = &$this->entries[$entry['entryName']];

            if (null === $resolved) {
                $resolved = $entry;
            }
        }

        return true;
    }

    public function open(): OpenedStore
    {
        return new OpenedStore(\array_values($this->entries));
    }

    /**
     * @param  string|NormalizedEntryDetails  $entry
     * @return NormalizedEntryDetails
     */
    private function normalizeEntry(string|array $entry): array
    {
        if (\is_string($entry)) {
            return [
                'filepath' => $entry,
                'entryName' => \basename($entry),
            ];
        }

        return $entry;
    }

    /**
     * @param  NormalizedEntryDetails  $entry
     *
     * @throws InvalidFilepathException
     * @throws InvalidEntryNameException
     */
    private function validateEntry(array $entry): void
    {
        if (false === is_file($entry['filepath'])) {
            throw new InvalidFilepathException;
        }

        if (false !== strpos($entry['entryName'], DIRECTORY_SEPARATOR)) {
            throw new InvalidEntryNameException;
        }
    }

    /**
     * @param  NormalizedEntryDetails  $entry
     * @return bool
     */
    private function validateEntryLoosely(array $entry): bool
    {
        try {
            $this->validateEntry($entry);

            return true;
        } catch (InvalidFilepathException|InvalidEntryNameException) {
            return false;
        }
    }
}
