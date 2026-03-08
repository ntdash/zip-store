<?php

namespace Tests\Concerns;

use Symfony\Component\Process\Process;
use Tests\Enums\Dirpath;
use Tests\Exceptions\FileIntegrityException;
use ZipStore\OpenedStore;
use ZipStore\Store;

trait HasStoreTestUtilities
{
    use HasFiles;

    /**
     * Validate that files inside an output directory match the provided SHA-256 hashes.
     *
     * Recursively checks files under the given output directory; for each file whose
     * relative path exists in `$inputHashes`, compares its SHA-256 hash to the
     * expected value and throws on mismatch. Entries not present in `$inputHashes`
     * are ignored.
     *
     * @param array<string,string> $inputHashes Map of relative file path (relative to `$output`) => expected SHA-256 hash.
     * @param string $output Path to the output directory to validate.
     *
     * @throws FileIntegrityException If any file's computed SHA-256 hash does not match the expected value.
     */
    private function checkOutputFilesIntegrity(array $inputHashes, string $output): void
    {
        $dir = new \RecursiveDirectoryIterator($output);
        $iter = new \RecursiveIteratorIterator($dir);

        $outputPathLen = \strlen($output);

        /** @var \SplFileInfo $entry */
        foreach ($iter as $entry) {
            if (false == $entry->isFile()) {
                continue;
            }

            // +1 for trailling directory separator
            $entryName = \substr($entry->getRealPath(), $outputPathLen + 1);

            if (! \array_key_exists($entryName, $inputHashes)) {
                continue;
            }

            $entryHash = \hash_file('sha256', $entry->getRealPath());

            if ($entryHash !== $inputHashes[$entryName]) {
                throw new FileIntegrityException(
                    "Hash mismatch for '{$entryName}' (expected: {$inputHashes[$entryName]}, actual: {$entryHash})"
                );
            }
        }

    }

    /**
     * Extracts a ZIP archive into the specified output directory after emptying that directory.
     *
     * @param string $archivePath Path to the ZIP archive to extract.
     * @param string $outputDir Directory where the archive will be extracted; this directory is emptied before extraction.
     * @throws \Exception If the unzip process fails (non-zero exit code).
     */
    private function deArchiveInto(string $archivePath, string $outputDir): void
    {
        $this->emptyDirectory($outputDir);

        $process = new Process(['unzip', $archivePath, '-d', $outputDir]);

        $exitCode = $process->run();

        if (0 !== $exitCode) {
            throw new \Exception(\sprintf(
                'Failed to de-archive %s\n[output]\n%s',
                $archivePath,
                $process->getErrorOutput() ?: $process->getOutput()
            ));
        }

    }

    /**
     * Remove all contents of the specified directory.
     *
     * @param string $dirpath Path to the directory to empty.
     * @throws \Exception If `$dirpath` does not point to an existing directory or if the removal command fails.
     */
    private function emptyDirectory(string $dirpath): void
    {
        if (! \is_dir($dirpath)) {
            throw new \Exception('$dirpath is not a directory path');
        }

        $process = new Process(['/usr/bin/rm', '-rf', $dirpath]);

        $exitCode = $process->run();

        if (0 !== $exitCode) {
            throw new \Exception("Failed to empty directory at {$dirpath}");
        }
    }

    /**
         * Adds predefined test input files to the provided store and returns their SHA-256 checksums.
         *
         * @param Store $store The target store to which test input files will be added.
         * @return array<string,string> Mapping of each input file's basename to its SHA-256 checksum.
         */
    private function fillStoreWithTestFiles(Store $store): array
    {
        $files = $this->getInputsFiles();

        $in_hashes = $this->generateFilesSHA256Checksum($files);

        /* store files through $this->store */
        $store->addFiles($files);

        return $in_hashes;
    }

    /**
         * Compute SHA-256 checksums for the given file paths.
         *
         * Files that cannot be hashed are omitted from the result.
         *
         * @param array<int,string> $filepaths List of file paths to hash.
         * @return array<string,string> An associative array mapping each file's basename to its SHA-256 hex digest.
         */
    private function generateFilesSHA256Checksum(array $filepaths): array
    {
        /** @var array<string,string> */
        $output = [];

        foreach ($filepaths as $filepath) {

            $hashed_value = hash_file('sha256', $filepath);

            if (false !== $hashed_value) {
                $output[\basename($filepath)] = $hashed_value;
            }
        }

        return $output;
    }

    /**
     * Resolve the test archive file path and ensure its parent directory exists.
     *
     * If a file already exists at the resolved path it will be removed.
     *
     * @return string The resolved archive file path.
     * @throws \Exception If the archive file's parent directory does not exist.
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
     * Resolve and ensure the test output directory exists, creating it when missing.
     *
     * @return string The resolved output directory path.
     * @throws \Exception If the resolved path exists as a file or the directory cannot be created.
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

    /**
     * Write the contents of an OpenedStore into a file on disk.
     *
     * The method reads buffers from the provided OpenedStore and writes them sequentially to
     * the file at $archivePath. If $append is true, data is appended to the file; otherwise the
     * file is overwritten.
     *
     * @param OpenedStore $openedStore The opened store to read data from.
     * @param string $archivePath Filesystem path to the target archive file.
     * @param bool $append When true, open the file in append mode; when false, overwrite the file.
     *
     * @throws \Exception If the archive file cannot be opened, if a buffer cannot be fully written,
     *                    or if the final file size does not match the store's reported size.
     */
    private function writeStoreInto(OpenedStore $openedStore, string $archivePath, bool $append = false): void
    {
        $stream = \fopen($archivePath, $append ? 'a' : 'w');

        if (! \is_resource($stream)) {
            throw new \Exception("Failed to open archive at {$archivePath}");
        }

        try {
            while (true) {
                $buffer = $openedStore->read(throw: true);

                if ($buffer->isEmpty()) {
                    break;
                }

                $written = \fwrite($stream, $buffer, $buffer->size);

                if ($written !== $buffer->size) {
                    throw new \Exception('Failed to write buffer into archive file');
                }
            }

            \fflush($stream);
        } finally {
            \fclose($stream);
        }

        \clearstatcache(true, $archivePath);

        if ($openedStore->getSize() !== \filesize($archivePath)) {
            throw new \Exception('Final size of the resulting file is not equal to the size of the virual one');
        }

    }
}
