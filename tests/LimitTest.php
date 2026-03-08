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

    /** @var array<array{'stream':resource,'filepath':string}> */
    private array $files = [];

    protected function tearDown(): void
    {
        foreach ($this->files as $file) {
            if (\is_resource($file['stream'])) {
                \fclose($file['stream']);
            }

            \unlink($file['filepath']);
        }
    }

    #[Test]
    #[TestDox('Adding large file exceeding set limit should failed')]
    public function handle_large_entry_file(): void
    {
        $this->files[] = $file = $this->createTempFile('both');

        $exceedingLimit = (int) (Store::ENTRY_MAX_FILESIZE + 100);

        if (false === \ftruncate($file['stream'], $exceedingLimit)) {
            throw new \Exception('failed to create a big file exceeding the limit size');
        }

        \clearstatcache(true, $file['filepath']);

        $store = new Store(Store::STRICT);

        $this->expectException(FileTooLargeException::class);

        $store->addFile($file['filepath']);
    }

    #[Test]
    #[TestDox('Adding big files that result into store size exceeding the set limit should failed')]
    public function handle_large_store(): void
    {
        $this->files[] = $file1 = $this->createTempFile('both');
        $this->files[] = $file2 = $this->createTempFile('both');

        $limit = Store::ENTRY_MAX_FILESIZE;

        foreach ([$file1, $file2] as $carry) {
            if (false === \ftruncate($carry['stream'], $limit)) {
                throw new \Exception('failed to create a big file');
            }

            \clearstatcache(true, $carry['filepath']);
        }

        $store = new Store(Store::STRICT);

        $store->addFiles([$file1['filepath'], $file2['filepath']]);

        $this->expectException(FileTooLargeException::class);

        $store->open();
    }

    #[Test]
    #[TestDox('Adding more files than the set limit should failed')]
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
