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

    private function resolveArchivePath(): string
    {
        $path = \tests_path(valueOf(Dirpath::ARCHIVE));
        $dirpath = \dirname($path);

        if (! \is_dir($dirpath)) {
            throw new \Exception('Archive filepath parent not found');
        }

        if (\is_file($path)) {
            \unlink($path);
        }

        return $path;
    }

    private function resolveOutputPath(): string
    {
        $path = \tests_path(valueOf(Dirpath::OUTPUT));

        if (\is_file($path)) {
            throw new \Exception('Expect output to be a directory path, but file path given');
        }

        if (! \file_exists($path)) {
            $created = \mkdir($path, recursive: true, permissions: 0o755);

            if (! $created) {
                throw new \Exception('Failed to resolve output directory path');
            }
        }

        return $path;
    }
}
