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
     * @param  array<string,string>  $inputHashes
     *
     * @throws FileIntegrityException
     */
    private function checkOutputFilesIntegrity(array $inputHashes, string $output): void
    {
        $dir = new \RecursiveDirectoryIterator($output);
        $iter = new \RecursiveIteratorIterator($dir);

        $outputPathLen = \strlen($output);

        /** @var \SplFileInfo $entry */
        foreach ($iter as $entry) {
            if (false === $entry->isFile()) {
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

    private function emptyDirectory(string $dirpath): void
    {
        if (! \is_dir($dirpath)) {
            throw new \Exception('$dirpath is not a directory path');
        }

        $process = new Process(['/usr/bin/sh', '-c', "rm -rf {$dirpath}/*"]);

        $exitCode = $process->run();

        if (0 !== $exitCode) {
            throw new \Exception("Failed to empty directory at {$dirpath}");
        }
    }

    /**
     * @return array<string,string>
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
     * @param  array<int,string>  $filepaths
     * @return array<string,string>
     */
    private function generateFilesSHA256Checksum(array $filepaths): array
    {
        /** @var array<string,string> */
        $output = [];

        foreach ($filepaths as $filepath) {

            $hashed_value = \hash_file('sha256', $filepath);

            if (false !== $hashed_value) {
                $output[\basename($filepath)] = $hashed_value;
            }
        }

        return $output;
    }

    private function resolveArchivePath(): string
    {
        $path = \tests_path(\valueOf(Dirpath::ARCHIVE));
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
        $path = \tests_path(\valueOf(Dirpath::OUTPUT));

        if (\is_file($path)) {
            throw new \Exception('Expect output to be a directory path, but file path given');
        }

        if (! \file_exists($path)) {
            $created = \mkdir($path, 0o755, true);

            if (! $created) {
                throw new \Exception('Failed to resolve output directory path');
            }
        }

        return $path;
    }

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
            throw new \Exception('Final size of the resulting file is not equal to the size of the virtual one');
        }

    }
}
