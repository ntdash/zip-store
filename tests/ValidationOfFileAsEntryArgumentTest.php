<?php

namespace Tests;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\Attributes\TestDox;
use PHPUnit\Framework\TestCase;
use ZipStore\Exceptions\InvalidEntryNameException;
use ZipStore\Exceptions\InvalidFilepathException;
use ZipStore\Store;

#[CoversClass(Store::class)]
class ValidationOfFileAsEntryArgumentTest extends TestCase
{
    private Store $store;

    protected function setUp(): void
    {
        $this->store = new Store(Store::STRICT);
    }

    protected function tearDown(): void
    {
        unset($this->store);
    }

    #[Test]
    #[TestDox('invalid entryName argument throw an exception')]
    public function handle_entry_name(): void
    {

        $filepath = tests_path('_data/input/map.json');
        $badEntryName = './map2.json';

        $this->assertFileExists($filepath, 'File should exist');

        $this->expectException(InvalidEntryNameException::class);

        $this->store->addFile($filepath, $badEntryName);
    }

    #[Test]
    #[TestDox('invalid filepath argument throw an exception')]
    public function handle_filepath(): void
    {
        $filepath = './should-not-exit';

        $this->assertFileDoesNotExist($filepath, 'File should not exist');

        $this->expectException(InvalidFilepathException::class);

        $this->store->addFile($filepath);
    }
}
