<?php

namespace Tests\Concerns;

use PHPUnit\Framework\TestCase;

/**
 * @mixin TestCase
 */
trait HasFiles
{
    /** @return array<int,string> */
    private function getFiles(string $path, bool $allow_empty = false): array
    {
        $files = \glob($path.'/*');

        $this->assertIsArray($files, 'Retrieved filepaths from @glob should be an array');

        if (! $allow_empty) {
            $this->assertNotEmpty($files, 'Retrieved filepaths should not be an empty array');
        }

        return $files;
    }

    /** @return list<string> */
    private function getInputsFiles(): array
    {
        static $files;

        return $files ??= $this->getFiles(tests_path('_data/input'));
    }
}
