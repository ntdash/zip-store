<?php

namespace Tests;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use SplFileInfo;
use Tests\Concerns\HasTestUtilities;
use ZipStore\OpenedStore;
use ZipStore\Store;

#[CoversClass(Store::class)]
class PartialReadingAfterPostSerializationTest extends TestCase
{
    use HasTestUtilities;

    private SplFileInfo $archivePath;

    /** @var array<string,string> */
    private array $input_hashes;

    private string $serialiazedOpenedStore;

    protected function setUp(): void
    {
        $store = new Store;
        $this->archivePath = $this->getArchivePath();
        $this->input_hashes = $this->addInputFilesIntoStore($store);

        $openedStore = $store->open();
        $this->partialReading($openedStore);

        $this->serialiazedOpenedStore = \serialize($openedStore);
    }

    public function test_partial_reading_after_deserialiazation(): void
    {
        /** @var OpenedStore */
        $openedStore = \unserialize($this->serialiazedOpenedStore);

        $this->writeStoreInto($openedStore, $this->archivePath, append: true);

        $this->postCompressionTask($this->archivePath, $this->input_hashes);
    }

    private function partialReading(OpenedStore $openedStore): void
    {
        $storeSize = $openedStore->getSize();
        $toBeReadSize = (int) \floor(\random_int((int) ($storeSize / 3), (int) ($storeSize / 2)));

        $bufferSize = (int) \floor((int) $toBeReadSize / 3);
        $leftSize = $toBeReadSize;

        $stream = fopen($this->archivePath->getPathname(), 'w');
        $this->assertIsResource($stream);

        try {
            while ($leftSize > 0) {
                $buffer = $openedStore->read(min($bufferSize, $leftSize), throw: true);

                if ($buffer->isEmpty()) {
                    break;
                }

                $leftSize -= $written = \fwrite($stream, $buffer, $buffer->size);

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

        $this->assertSame(
            $toBeReadSize,
            $this->archivePath->getSize(),
            'Final size of the resulting file is not equal to the size to be read'
        );

    }
}
