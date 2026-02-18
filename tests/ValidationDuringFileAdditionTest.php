<?php

namespace Tests;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use ZipStore\Exceptions\InvalidEntryNameException;
use ZipStore\Exceptions\InvalidFilepathException;
use ZipStore\Exceptions\ValidationException;
use ZipStore\Store;

#[CoversClass(Store::class)]
class ValidationDuringFileAdditionTest extends TestCase
{
    private Store $store;

    protected function setUp(): void
    {
        $this->store = new Store;
    }

    protected function tearDown(): void
    {
        unset($this->store);
    }

    public function test_validate_entry_name_argument(): void {

        $filepath = tests_path("_data/input/map.json");
        $badEntryName = "./map2.json";

        $this->assertFileExists($filepath, "File should exist");

        $this->expectException(InvalidEntryNameException::class);

        $this->store->addFile($filepath, $badEntryName);
    }

    public function test_validate_filepath_argument(): void
    {
        $filepath = './should-not-exit';

        $this->assertFileDoesNotExist($filepath, 'File should not exist');

        $this->expectException(InvalidFilepathException::class);

        $this->store->addFile($filepath);
    }
}
