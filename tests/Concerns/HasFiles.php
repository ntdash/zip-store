<?php

namespace Tests\Concerns;

trait HasFiles
{
    /**
     * @param  'both'|'resource'|'filepath'  $returnMode
     * @return ($returnMode is 'both' ? array{0:resource,1:string} : ($returnMode is 'filepath' ? string : resource))
     *
     * @throws \Exception
     */
    private function createTempFile(string $returnMode): mixed
    {
        $handle = \tmpfile();
        $filepath = false;

        if (!\is_resource($handle)) {
            throw new \Exception('tmpfile error');
        }

        switch ($returnMode) {
            case 'filepath':
            case 'both':
                $details = \stream_get_meta_data($handle);

                if (! isset($details['uri']) || ! \is_file($details['uri']) || ! ($filepath = \realpath($details['uri']))) {
                    throw new \Exception('failed to obtain @tmpfile filepath');
                }

                return 'filepath' == $returnMode ? $filepath : [$handle, $filepath];
        }

        return $handle;
    }

    /**
     * @return array<string>
     */
    private function getFiles(string $path, bool $allow_empty = false): array
    {
        $files = \glob($path.'/*');

        if (false === $files) {
            throw new \Exception('glob error');
        } elseif (empty($files)) {
            if ($allow_empty) {
                return [];
            }
            throw new \Exception('Files not found');
        }

        return $files;
    }

    /**
     * @return array<string>
     */
    private function getInputsFiles(): array
    {
        static $files;

        return $files ??= $this->getFiles(\tests_path('_data/input'));
    }
}
