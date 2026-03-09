<?php

namespace Tests\Support;

use Tests\Concerns\HasStoreTestUtilities;
use Tests\Enums\Dirpath;
use ZipStore\OpenedStore;

class ConformityChecker
{
    use HasStoreTestUtilities;

    /**
     * @param  array<string,string>  $inputHashes
     */
    public function __construct(
        private readonly array $inputHashes,
        private readonly OpenedStore $openedStore
    ) {}

    public function check(): void
    {
        $archivePath = $this->resolveArchivePath();
        $outputPath = $this->resolveOutputPath();

        // write OpenedStore into file
        $this->writeStoreInto($this->openedStore, $archivePath);

        // de-archive with official program: unzip
        $this->deArchiveInto($archivePath, $outputPath);

        // then check input hashes against de-archived files
        $this->checkOutputFilesIntegrity($this->inputHashes, $outputPath);
    }
}
