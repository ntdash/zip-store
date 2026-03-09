<?php

namespace Tests;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\Attributes\TestDox;
use PHPUnit\Framework\TestCase;
use SplFileInfo;
use Tests\Concerns\HasFiles;
use Tests\Concerns\HasStoreTestUtilities;
use Tests\Exceptions\FileIntegrityException;
use ZipStore\OpenedStore;
use ZipStore\Store;

#[CoversClass(Store::class)]
class PartialReadingAfterPostSerializationTest extends TestCase
{
    use HasFiles;
    use HasStoreTestUtilities;

    private \SplFileInfo $archivePath;

    /** @var array<string,string> */
    private array $inputHashes;

    private string $serialiazedOpenedStore;

    protected function setUp(): void
    {
        $store = new Store;
        $this->inputHashes = $this->fillStoreWithTestFiles($store);
        $this->archivePath = new SplFileInfo($this->resolveArchivePath());

        $openedStore = $store->open();
        $this->partialRead($openedStore);

        $this->serialiazedOpenedStore = \serialize($openedStore);
    }

    #[Test]
    #[TestDox('Partial reading after deserialiazation')]
    public function handle(): void
    {
        /** @var OpenedStore */
        $openedStore = \unserialize($this->serialiazedOpenedStore);

        $this->writeStoreInto($openedStore, $this->archivePath, append: true);

        $this->assertTrue(
            $this->postCompressionTask(),
            'Output files integrity check failed'
        );
    }

    private function partialRead(OpenedStore $openedStore): void
    {
        $storeSize = $openedStore->getSize();
        $toBeReadSize = (int) \floor(\random_int((int) ($storeSize / 3), (int) ($storeSize / 2)));

        $bufferSize = (int) \floor((int) $toBeReadSize / 3);
        $leftSize = $toBeReadSize;

        $stream = \fopen($this->archivePath->getPathname(), 'w');

        if (! \is_resource($stream)) {
            throw new \Exception('Failed to open file');
        }

        try {
            while ($leftSize > 0) {
                $buffer = $openedStore->read(min($bufferSize, $leftSize), throw: true);

                if ($buffer->isEmpty()) {
                    break;
                }

                $leftSize -= $written = \fwrite($stream, $buffer, $buffer->size);

                if ($written !== $buffer->size) {
                    throw new \Exception('Failed to write read content into buffer');
                }
            }

            \fflush($stream);
        } finally {
            \fclose($stream);
        }

        \clearstatcache(true, $this->archivePath->getRealPath());

        if ($this->archivePath->getSize() !== $toBeReadSize) {
            throw new \Exception('Mismatch between archive size and $toBeRead size');
        }
    }

    private function postCompressionTask(): bool
    {
        try {
            $outputPath = $this->resolveOutputPath();

            $this->deArchiveInto($this->archivePath, $outputPath);

            $this->checkOutputFilesIntegrity($this->inputHashes, $outputPath);
        } catch (FileIntegrityException) {
            return false;
        }

        return true;
    }
}
