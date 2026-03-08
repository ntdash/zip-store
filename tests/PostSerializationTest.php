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
class PostSerializationTest extends TestCase
{
    use HasStoreTestUtilities;

    /** @var array<string,string> */
    private array $inputHashes;

    private string $serializedOpenedStore;

    protected function setUp(): void
    {
        $store = new Store;
        $this->inputHashes = $this->fillStoreWithTestFiles($store);
        $this->serializedOpenedStore = \serialize($store->open());
    }

    #[Test]
    #[TestDox('Conformity check after deserialization')]
    public function handle(): void
    {
        /** @var OpenedStore */
        $openedStore = \unserialize($this->serializedOpenedStore);

        $this->assertTrue(
            $this->check($openedStore),
            'Conformity check failed'
        );
    }

    private function check(OpenedStore $openedStore): bool
    {
        $checker = new ConformityChecker($this->inputHashes, $openedStore);

        try {
            $checker->check();
        } catch (FileIntegrityException) {
            return false;
        }

        return true;
    }
}
