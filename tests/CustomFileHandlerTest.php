<?php

namespace Tests;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\Attributes\TestDox;
use PHPUnit\Framework\TestCase;
use Tests\Support\DBFile;
use Tests\Support\FileTable;
use ZipStore\EntryArgument;
use ZipStore\Store;

#[CoversClass(Store::class)]
class CustomFileHandlerTest extends TestCase
{
    private FileTable $table;

    protected function setUp(): void
    {
        $this->table = FileTable::load();
    }

    protected function tearDown(): void
    {
        unset($this->table);
        EntryArgument::setEntryFileClass();
    }

    #[Test]
    #[TestDox('reading file from database')]
    public function handle_db_file(): void
    {
        EntryArgument::setEntryFileClass(DBFile::class);

        $fileCount = 4;
        $toString = fn (mixed $v) => (string) $v;

        $store = new Store;

        $store->addFiles(array_map($toString, range(1, $fileCount)));

        $entries = $store->getEntries();
        $this->assertCount($fileCount, $entries);

    }
}
