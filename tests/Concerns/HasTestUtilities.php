<?php

namespace Tests\Concerns;

use SplFileInfo;
use Symfony\Component\Process\Process;
use ZipStore\OpenedStore;
use ZipStore\Store;
use ZipStore\Supports\StringBuffer;

trait HasTestUtilities
{
    use HasFiles;

    /**
     *
     * @param Store $store
     * @return array<string,string>
     */
    private function addInputFilesIntoStore(Store $store): array
    {
        $files = $this->getInputsFiles();

        /* hash files to be add to zip store and compressed by og_zip software */
        $in_hashes = $this->generateFilesSHA256Checksum($files);

        /* store files through $this->store */
        $store->addFiles($files);

        return $in_hashes;
    }

    /** @param array<string,string> $input_hashes*/
    private function check_conformity(array $input_hashes, OpenedStore $openedStore): void
    {
        $archivePath = $this->getArchivePath();

        $this->writeStoreInto($openedStore, $archivePath);

        /* uncompressed the newly written file using @unzip command into tests_path('_data/output') */

        $this->postCompressionTask($archivePath, $input_hashes);
    }

    private function emptyDir(SplFileInfo $directory): void
    {
        if (! $directory->isDir()) {
            return;
        }

        $process = new Process(['/usr/bin/rm', '-rf', $directory->getRealPath()]);

        $process->start();

        $process->wait();

        $this->assertEquals(0, $process->getExitCode(), \sprintf(
            "Failed to empty output folder\n[output]\n%s",
            $process->getErrorOutput() ?: $process->getOutput()
        ));

    }

    /**
     * @param  array<int,string>  $filepaths
     * @return array<string,string>
     * */
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

    private function getArchivePath(): SplFileInfo
    {
        /* write $this->store output into tests_path('_data/archive.zip') */
        $archivePath = new SplFileInfo(tests_path('_data/archive.zip'));

        $this->assertTrue(
            \is_dir($archivePath->getPath()),
            'Invalid path for ZipStore to be written into'
        );

        if ($archivePath->isFile()) {
            \unlink($archivePath);
        }

        return $archivePath;
    }

    /**
     * @param  array<string,string>  $input_hashes
     */
    private function postCompressionTask(SplFileInfo $archivePath, array $input_hashes): void
    {
        $extract_path = new SplFileInfo(tests_path('_data/output'));
        $this->unCompressArchiveInto($archivePath, $extract_path);

        /* compute the hashes of newly uncompressed files */
        $out_hashes = $this->generateFilesSHA256Checksum($this->getFiles($extract_path));

        /* assert that in_hashes and out_hashes are the same */
        $this->assertEquals($input_hashes, $out_hashes);
    }

    private function unCompressArchiveInto(SplFileInfo $archivePath, SplFileInfo $outputDir): void
    {
        $this->emptyDir($outputDir);

        $process = new Process([
            'unzip', $archivePath->getRealPath(), '-d', $outputDir->getPathname(),
        ]);

        $process->start();

        $process->wait();

        $this->assertEquals(0, $process->getExitCode(), \sprintf(
            "Process seem to have failed\n[output]\n%s",
            $process->getErrorOutput() ?: $process->getOutput()
        ));

    }

    private function writeStoreInto(OpenedStore $openedStore, SplFileInfo $archivePath, bool $append = false): void
    {
        $stream = \fopen($archivePath->getPathname(), $append ? 'a' : 'w');

        $this->assertIsResource($stream);

        try {
            while (true) {
                $buffer = $openedStore->read();

                $this->assertInstanceOf(
                    StringBuffer::class,
                    $buffer,
                    'Successfull @read action on Zipstore should always return a StringBuffer::class'
                );

                if ($buffer->isEmpty()) {
                    break;
                }

                $written = \fwrite($stream, $buffer, $buffer->size);

                $this->assertEquals(
                    $buffer->size,
                    $written,
                    'Failed to write buffer into archive file'
                );
            }

            \fflush($stream);
        } finally {
            \fclose($stream);
        }

        $this->assertEquals(
            $openedStore->getSize(),
            $archivePath->getSize(),
            'Final size of the resulting file is not equal to the size of the virual one'
        );

    }
}
