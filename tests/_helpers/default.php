<?php

if (! \function_exists('base_path')) {

    /**
     * Get the project base directory path, optionally joined with a relative path.
     *
     * The returned string ends with a '/' followed by the provided `$path` (which may be empty).
     *
     * @param string $path Path relative to the project base.
     * @return string The base directory path concatenated with the provided `$path`.
     */
    function base_path(string $path = ''): string
    {
        static $basepath;

        return ($basepath ??= dirname(dirname(__DIR__))).'/'.$path;
    }
}

if (! \function_exists('tests_path')) {

    /**
     * Get the absolute path to the repository's tests directory.
     *
     * @param string $path Optional relative path inside the tests directory.
     * @return string The full path to the tests directory joined with the provided $path.
     */
    function tests_path(string $path = ''): string
    {
        return base_path('tests/'.$path);
    }
}

if (! \function_exists('valueOf')) {
    /**
     * Normalize a value by extracting the scalar value from a BackedEnum when present.
     *
     * @param mixed $carry The value to normalize; if a BackedEnum instance, its backed scalar value will be returned.
     * @return mixed The backed scalar value for a BackedEnum, or the original value otherwise.
     */
    function valueOf(mixed $carry): mixed
    {
        return $carry instanceof BackedEnum
            ? $carry->value
            : $carry;
    }
}
