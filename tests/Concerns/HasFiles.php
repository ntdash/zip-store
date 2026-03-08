<?php

namespace Tests\Concerns;

trait HasFiles
{
    /**
     * Create a temporary file stream and optionally return its filepath or both the stream and filepath.
     *
     * @param 'both'|'resource'|'filepath' $returnMode Mode controlling the return value: 'filepath' to return the temporary file path, 'both' to return an associative array with the stream and filepath, any other value to return the stream resource.
     * @return ($returnMode is 'both' ? array{'stream':resource,'filepath':string} : ($returnMode is 'filepath' ? string : resource))
     *
     * @throws \Exception If tmpfile() fails or the temporary file path cannot be determined.
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
     * Collects file paths for regular files inside the given directory.
     *
     * @param string $path Directory path to scan for files.
     * @param bool $allow_empty If true, an empty list is returned when no files are found; otherwise an exception is thrown.
     * @return list<string> List of file paths for entries that are regular files.
     * @throws \Exception If the underlying glob call fails.
     * @throws \Exception If no files are found and `$allow_empty` is false.
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
     * Retrieve the list of input data file paths from the tests/_data/input directory and cache it for subsequent calls.
     *
     * @return list<string> The list of file paths contained in the input data directory.
     */
    private function getInputsFiles(): mixed
    {
        static $files;

        return $files ??= $this->getFiles(\tests_path('_data/input'));
    }
}
