<?php

namespace Tests;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Tests\Concerns\HasTestUtilities;
use ZipStore\OpenedStore;
use ZipStore\Store;

#[CoversClass(Store::class)]
class PostSerializationTest extends TestCase
{
    use HasTestUtilities;

    /** @var array<string,string> */
    private array $input_hashes;

    private string $serializedOpenedStore;

    protected function setUp(): void
    {
        $store = new Store;
        $this->input_hashes = $this->addInputFilesIntoStore($store);
        $this->serializedOpenedStore = \serialize($store->open());
    }

    public function test_confirmity_after_deserialization(): void
    {
        /** @var OpenedStore */
        $openedStore = \unserialize($this->serializedOpenedStore);

        $this->check_conformity($this->input_hashes, $openedStore);
    }
}
