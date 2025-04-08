<?php

namespace Tests;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use SplFileInfo;
use SplFileObject;
use Symfony\Component\Process\Process;
use Tests\Concerns\HasFiles;
use ZipStore\Store;
use ZipStore\Supports\StringBuffer;

#[CoversClass(Store::class)]
class ZipStoreTest extends TestCase
{
    use HasFiles;

    private Store $store;

    protected function setUp(): void
    {
        $this->store = new Store;
    }

    protected function tearDown(): void
    {
        unset($this->store);
    }

    public function test_uniformity_between_official_software_output(): void
    {
        $files = $this->getInputsFiles();

        /* hash files to be add to zip store and compressed by og_zip software */
        $in_hashes = $this->generateFilesSHA256Checksum($files);

        /* store files through $this->store */
        $this->store->addFiles($files);

        /* write $this->store output into tests_path('_data/archive.zip') */
        $archivePath = new SplFileInfo(tests_path('_data/archive.zip'));

        $this->assertTrue(
            is_dir($archivePath->getPath()),
            'Invalid path for ZipStore to be written into'
        );

        if (is_file($archivePath)) {
            unlink($archivePath);
        }

        $this->writeStoreInto($archivePath);

        /* uncompressed the newly written file using @unzip command into tests_path('_data/output') */

        $extract_path = new SplFileInfo(tests_path('_data/output'));
        $this->unCompressArchiveInto($archivePath, $extract_path);

        /* compute the hashes of newly uncompressed files */
        $out_hashes = $this->generateFilesSHA256Checksum($this->getFiles($extract_path));

        /* assert that in_hashes and out_hashes are the same */
        $this->assertEquals($in_hashes, $out_hashes);
    }

    private function emptyDir(SplFileInfo $directory): void
    {
        if (! $directory->isDir()) {
            return;
        }

        $process = new Process(['/usr/bin/rm', '-rf', $directory->getRealPath()]);

        $process->start();

        $process->wait();

        $this->assertEquals(0, $process->getExitCode(), sprintf(
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

    private function unCompressArchiveInto(SplFileInfo $archivePath, SplFileInfo $outputDir): void
    {
        $this->emptyDir($outputDir);

        $process = new Process([
            'unzip', $archivePath->getRealPath(), '-d', $outputDir->getPathname(),
        ]);

        $process->start();

        $process->wait();

        $this->assertEquals(0, $process->getExitCode(), sprintf(
            "Process seem to have failed\n[output]\n%s",
            $process->getErrorOutput() ?: $process->getOutput()
        ));

    }

    private function writeStoreInto(SplFileInfo $archivePath): void
    {
        $openedStore = $this->store->open();
        $handler = $archivePath->openFile('w');

        $this->assertInstanceOf(SplFileObject::class, $handler);

        while (true) {
            $read = $openedStore->read();

            $this->assertInstanceOf(
                StringBuffer::class,
                $read,
                'Successfull @read action on Zipstore should always return a StringBuffer::class'
            );

            if ($read->isEmpty()) {
                break;
            }

            $written = $handler->fwrite($read, $read->size);

            $this->assertEquals(
                $read->size,
                $written,
                'Failed to write read zip-store content into archive file'
            );
        }

        $this->assertSame(
            $openedStore->getSize(),
            $archivePath->getSize(),
            'Final size of the resulting file is not equal to the size of the virual one'
        );

        unset($handler);
    }
}
