<?php

namespace Tests;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\Attributes\TestDox;
use PHPUnit\Framework\TestCase;
use ZipStore\Exceptions\DuplicateEntryException;
use ZipStore\Store;
use ZipStore\Supports\EntryArgument;

#[CoversClass(Store::class)]
class DuplicateEntriesResolutionTest extends TestCase
{
    /** @var array<EntryArgument> */
    private array $inputs;

    protected function setUp(): void
    {
        $this->inputs = match ($this->name()) {
            'handle_numeric_suffix' => $this->numericSuffixTestInputs(),
            'handle_overwrite' => $this->overwriteTestInputs(),
            'handle_thrown' , 'handle_thrown_under_strict' => $this->thrownTestInputs(),
            default => []
        };
    }

    #[Test]
    #[TestDox('[DUP_APPEND_NUM], duplicated entries are resolved by appending numberic suffix')]
    public function handle_numeric_suffix(): void
    {
        $store = new Store(Store::DUP_APPEND_NUM);

        $en_input = array_map(fn ($entry) => $entry->entryName, $this->inputs);

        $store->addFiles($this->inputs);

        $ens = \array_map(
            fn ($entry) => $entry->entryName,
            $entries = $store->getEntries()
        );

        $fileteredEntryNames = \array_unique($ens);

        $this->assertEquals(
            \count($this->inputs),
            \count($fileteredEntryNames),
            'Count of inputs and resulting entryNames post unique filtering should be the same'
        );
    }

    #[Test]
    #[testdox('[DUP_OVERWRITE], duplicated entries are resovled by overwriting the last entry with the same resulting entryname')]
    public function handle_overwrite(): void
    {
        $store = new Store(Store::DUP_OVERWRITE);

        foreach ($this->inputs as $input) {
            $store->addFile($input);
        }

        $entries = $store->getEntries();

        $this->assertCount(
            1,
            $entries,
            'Only one entry should remain in the list after the operations'
        );

        $lastInput = $this->inputs[\count($this->inputs) - 1];
        $soleEntry = $entries[0];

        $this->assertEquals(
            $lastInput->filepath,
            $soleEntry->filepath,
            'The sole entry of the store should be the last added input'
        );
    }

    #[Test]
    #[TestDox('[DUP_THROW], duplicated entries are not resolve but exception are thrown upon seeing one')]
    public function handle_thrown(): void
    {
        $store = new Store(Store::DUP_FAILED);

        $added = $store->addFiles($this->inputs);

        $this->assertFalse(
            $added,
            'With the absence of the Store::STRICT flag, false should be return instead'
        );
    }

    #[Test]
    #[TestDox('[STRICT|THROW], duplicated entries are not resolve but exception are thrown upon seeing one')]
    public function handle_thrown_under_strict(): void
    {
        $store = new Store(Store::STRICT);

        $this->expectException(DuplicateEntryException::class);

        $store->addFiles($this->inputs);
    }

    /**
     * @return array<EntryArgument>
     */
    private function numericSuffixTestInputs(): array
    {
        $inputs = [];

        $dup_name = 'ename';
        $filepath = tests_path('_data/input/map.json');

        for ($i = 1; $i <= 100; $i++) {
            $entryName = $dup_name;

            if (0 === $i % 5) {
                $n_suf = \random_int(2, (5 * (int) ($i / 5)) - 1);

                $u_suffix = \str_pad((string) $n_suf, 2, '0', STR_PAD_LEFT);
                $entryName .= \sprintf('_%s', $u_suffix);
            }

            $inputs[] = new EntryArgument($filepath, $entryName);
        }

        return $inputs;
    }

    /**
     * @return array<EntryArgument>
     */
    private function overwriteTestInputs(): array
    {
        $inputs = [];

        $dup_name = 'ename';
        $filepaths = glob(tests_path('_data/input/*'));

        if (is_array($filepaths)) {
            foreach ($filepaths as $filepath) {
                $inputs[] = new EntryArgument($filepath, $dup_name);
            }
        }

        return $inputs;
    }

    /**
     * @return array<EntryArgument>
     */
    private function thrownTestInputs(): array
    {
        $dup_name = 'ename';
        $filepath = \tests_path('_data/input/map.json');

        for ($i = 1; $i <= 10; $i++) {
            $inputs[] = new EntryArgument($filepath, $dup_name);
        }

        return $inputs;
    }
}
