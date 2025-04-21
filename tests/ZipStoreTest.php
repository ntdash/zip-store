<?php

namespace Tests;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Tests\Concerns\HasTestUtilities;
use ZipStore\Store;

#[CoversClass(Store::class)]
class ZipStoreTest extends TestCase
{
    use HasTestUtilities;

    private Store $store;

    protected function setUp(): void
    {
        $this->store = new Store;
    }

    protected function tearDown(): void
    {
        unset($this->store);
    }

    public function test_conformity_with_official_software_output(): void
    {
        $input_hashes = $this->addInputFilesIntoStore($this->store);

        $this->check_conformity($input_hashes, $this->store->open());
    }
}
