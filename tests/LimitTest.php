<?php

namespace Tests;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\Attributes\TestDox;
use PHPUnit\Framework\TestCase;
use Tests\Concerns\HasFiles;
use ZipStore\Exceptions\EntriesOverflowException;
use ZipStore\Exceptions\FileTooLargeException;
use ZipStore\Store;

#[CoversClass(Store::class)]
class LimitTest extends TestCase
{
    use HasFiles;

    #[Test]
    #[TestDox("adding large file exceeding set limit should failed")]
    public function handle_large_entry_file(): void
    {
        [$stream, $filepath] = $this->createTempFile('both');

        $exceedingLimit = (int) (Store::ENTRY_MAX_FILESIZE + 100);

        if (false === \ftruncate($stream, $exceedingLimit)) {
            throw new \Exception('failed to create a big file exceeding the limit size');
        }

        $store = new Store(Store::STRICT);

        $this->expectException(FileTooLargeException::class);

        $store->addFile($filepath);
    }

    #[Test]
    #[TestDox("adding big files that result into store size exceeding the set limit should failed")]
    public function handle_large_store(): void
    {
        [$stream, $filepath] = $this->createTempFile('both');
        [$stream2, $filepath2] = $this->createTempFile('both');

        $limit = Store::ENTRY_MAX_FILESIZE;

        foreach([$stream, $stream2] as $carry)
            if (false === ftruncate($carry, $limit))
                throw new \Exception("failed to create a big file");

        $store = new Store(Store::STRICT);

        $store->addFiles([$filepath, $filepath2]);

        $this->expectException(FileTooLargeException::class);

        $store->open();
    }

    #[Test]
    #[TestDox("adding more files than the set limit should failed")]
    public function handle_max_entries(): void
    {
        $store = new Store(Store::STRICT);

        $filepath = \tests_path('_data/input/map.json');
        $basename = 'ename';

        $limit = Store::ENTRIES_LIMIT + 10;

        $this->expectException(EntriesOverflowException::class);

        for ($i = 0; $i < $limit; $i++) {
            $entryName = \sprintf(
                '%s_%s',
                $basename,
                \str_pad((string) $i, 2, '0', STR_PAD_LEFT)
            );

            $store->addFile($filepath, $entryName);
        }
    }
}
