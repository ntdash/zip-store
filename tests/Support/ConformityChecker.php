<?php

namespace Tests\Support;

use Tests\Concerns\HasStoreTestUtilities;
use Tests\Enums\Dirpath;
use ZipStore\OpenedStore;

class ConformityChecker
{
    use HasStoreTestUtilities;

    /**
         * Initialize the ConformityChecker with expected file hashes and an opened store.
         *
         * @param array<string,string> $inputHashes Mapping from input file identifier to expected hash.
         * @param OpenedStore $openedStore The opened store instance whose contents will be written to an archive for verification.
         */
    public function __construct(
        private readonly array $inputHashes,
        private readonly OpenedStore $openedStore
    ) {}

    /**
     * Performs a full archive round-trip and verifies the integrity of extracted files.
     *
     * Writes the provided opened store to an archive file, extracts that archive into the resolved
     * output directory, and compares the extracted files' hashes against the expected input hashes.
     */
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

    /**
     * Resolve and prepare the filesystem path for the archive file.
     *
     * Ensures the archive's parent directory exists and removes any existing file at the archive path.
     *
     * @return string The resolved archive file path.
     * @throws \Exception If the archive filepath parent directory does not exist.
     */
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

    /**
     * Resolve and ensure the output directory exists and return its path.
     *
     * Ensures the configured output path is a directory; if it does not exist the directory is created with 0755 permissions.
     *
     * @return string The resolved output directory path.
     * @throws \Exception If the resolved path is an existing file, or if creating the directory fails.
     */
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
