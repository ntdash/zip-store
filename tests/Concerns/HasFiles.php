<?php

namespace Tests\Concerns;

trait HasFiles
{
    /**
     * @param  'both'|'resource'|'filepath'  $returnMode
     * @return ($returnMode is 'both' ? array{'stream':resource,'filepath':string} : ($returnMode is 'filepath' ? string : resource))
     *
     * @throws \Exception
     */
    private function createTempFile(string $returnMode): mixed
    {
        $stream = \tmpfile();
        $filepath = false;

        if (! \is_resource($stream)) {
            throw new \Exception('tmpfile error');
        }

        switch ($returnMode) {
            case 'filepath':
            case 'both':
                $details = \stream_get_meta_data($stream);

                if (! isset($details['uri']) || ! \is_file($details['uri']) || ! ($filepath = \realpath($details['uri']))) {
                    throw new \Exception('failed to obtain @tmpfile filepath');
                }

                return 'filepath' == $returnMode ? $filepath : compact('stream', 'filepath');
        }

        return $stream;
    }

    /**
     * @return list<string>
     */
    private function getFiles(string $path, bool $allow_empty = false): mixed
    {
        $entries = \glob($path.'/*');

        if (false === $entries) {
            throw new \Exception('glob error');
        }

        /** @var list<string> */
        $files = [];

        foreach ($entries as $entry) {
            if (\is_file($entry)) {
                $files[] = $entry;
            }
        }

        if (empty($files) && ! $allow_empty) {
            throw new \Exception('Files not found');
        }

        return $files;
    }

    /**
     * @return list<string>
     */
    private function getInputsFiles(): mixed
    {
        static $files;

        return $files ??= $this->getFiles(\tests_path('_data/input'));
    }
}
