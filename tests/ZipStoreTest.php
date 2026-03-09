<?php

namespace Tests;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\Attributes\TestDox;
use PHPUnit\Framework\TestCase;
use Tests\Concerns\HasStoreTestUtilities;
use Tests\Exceptions\FileIntegrityException;
use Tests\Support\ConformityChecker;
use ZipStore\OpenedStore;
use ZipStore\Store;

#[CoversClass(Store::class)]
class ZipStoreTest extends TestCase
{
    use HasStoreTestUtilities;

    /** @var array<string,string> */
    private array $inputHashes;

    private Store $store;

    protected function setUp(): void
    {
        $this->store = new Store;
        $this->inputHashes = $this->fillStoreWithTestFiles($this->store);
    }

    protected function tearDown(): void
    {
        unset($this->store);
    }

    #[Test]
    #[TestDox('Conformity check with official software output')]
    public function handle(): void
    {
        $this->assertTrue(
            $this->check($this->store->open()),
            'Conformity check failed'
        );
    }

    /**
     * @throws FileIntegrityException
     */
    private function check(OpenedStore $openedStore): bool
    {
        $checker = new ConformityChecker($this->inputHashes, $openedStore);

        $checker->check();

        return true;
    }
}
