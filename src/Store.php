<?php

namespace ZipStore;

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

    public function addFile(string $filepath, ?string $entryName = null): void
    {
        $entryName ??= basename($filepath);
        $this->addFiles([compact('entryName', 'filepath')]);
    }

    /**
     * @param  list<string|NormalizedEntryDetails>  $entries
     */
    public function addFiles(array $entries): void
    {
        foreach ($entries as $entry) {

            $entry = $this->normalizeEntry($entry);

            if (empty($entry['filepath'])) {
                continue;
            }

            /** @var null|string */
            $resolved = &$this->entries[$entry['entryName']];

            if (null === $resolved) {
                $resolved = $entry;
            }
        }
    }

    public function open(): OpenedStore
    {
        return new OpenedStore(array_values($this->entries));
    }

    /**
     * @param  string|NormalizedEntryDetails  $entry
     * @return NormalizedEntryDetails
     */
    private function normalizeEntry(string|array $entry): array
    {

        if (is_string($entry)) {
            return [
                'filepath' => $entry,
                'entryName' => basename($entry),
            ];
        }

        return $entry;
    }
}
