<?php

namespace ZipStore;

use ZipStore\Contracts\ZipStoreEntryFile;
use ZipStore\Exceptions\FileNotFoundException;
use ZipStore\Exceptions\InvalidEntryFileClass;
use ZipStore\Exceptions\InvalidEntryNameException;
use ZipStore\Supports\LocalFile;

/**
 * @phpstan-type EntryDetails array{entryName?:string,identifier?:string}
 */
class EntryArgument
{
    public readonly string $entryName;

    public readonly ZipStoreEntryFile $file;

    /** @var class-string<ZipStoreEntryFile> */
    private static $entryFileClass = LocalFile::class;

    /**
     * Create an EntryArgument from either a string identifier or an associative details array.
     *
     * When a string is provided it is used as the file identifier and the optional
     * $entryName argument overrides the derived name; when an array is provided it
     * must contain a string 'identifier' and may contain an 'entryName' to override
     * the derived name.
     *
     * @param array|string $identifierOrDetails A string file identifier or an associative array with keys:
     *                                          - 'identifier' (string): required file identifier,
     *                                          - 'entryName' (string): optional entry name to use instead of the file basename.
     * @param string|null $entryName Optional entry name to use when the first argument is a string; if null the file basename is used.
     *
     * @throws FileNotFoundException If the array form is provided but the 'identifier' is missing or not a string.
     */
    public function __construct(array|string $identifierOrDetails, ?string $entryName = null)
    {
        if (\is_string($identifierOrDetails)) {
            $this->file = new self::$entryFileClass($identifierOrDetails);
            $this->entryName = $entryName ?? \basename($this->file->getFilename());

        } else {
            if (! \is_string($identifierOrDetails['identifier'] ?? null)) {
                throw new FileNotFoundException('wrong or missing identifier argument');
            }

            $this->file = new self::$entryFileClass($identifierOrDetails['identifier']);
            $this->entryName = $identifierOrDetails['entryName'] ?? \basename($this->file->getFilename());
        }
    }

    /**
     * Create a new EntryArgument using optional identifier and entry name overrides.
     *
     * @param null|string|array $identifier Identifier string or associative "EntryDetails" array; when null, uses the current file's identifier.
     * @param string|null $entryName Optional entry name override; when null, uses the current entryName.
     * @return self A new EntryArgument constructed with the resolved identifier and entry name.
     */
    public function clone(null|string|array $identifier = null, ?string $entryName = null): self
    {

        return new self($identifier ?? $this->file->getIdentifier(), $entryName ?? $this->entryName);
    }

    /**
         * Set the class used to create entry file instances, or reset it to the default.
         *
         * When given a class-string, the class must implement ZipStoreEntryFile and define
         * the magic serialization methods `__serialize` and `__unserialize`. Passing `null`
         * resets the entry file class to LocalFile::class.
         *
         * @param null|class-string $abstract The class to use for entry files, or `null` to reset to the default.
         * @throws InvalidEntryFileClass If the provided class does not implement ZipStoreEntryFile or is missing required serialization methods.
         * @return void
         */
    public static function setEntryFileClass(?string $abstract = null)
    {
        if (null === $abstract) {
            self::$entryFileClass = LocalFile::class;

            return;
        }

        if (! \is_a($abstract, ZipStoreEntryFile::class, true)) {
            throw new InvalidEntryFileClass(
                \sprintf('"%s" does not implement the "%s" interface', $abstract, ZipStoreEntryFile::class)
            );
        }

        foreach (['__serialize', '__unserialize'] as $s_method) {
            if (! \method_exists($abstract, $s_method)) {
                throw new InvalidEntryFileClass('Missing serialization magic methods');
            }
        }

        self::$entryFileClass = $abstract;
    }

    /**
     * Ensures the underlying file exists and the entry name does not contain a directory separator.
     *
     * @throws FileNotFoundException If the underlying entry file does not exist.
     * @throws InvalidEntryNameException If the entry name contains DIRECTORY_SEPARATOR.
     */
    public function validate(): void
    {
        if (false === $this->file->exists()) {
            throw new FileNotFoundException;
        }

        if (false !== \strpos($this->entryName, DIRECTORY_SEPARATOR)) {
            throw new InvalidEntryNameException;
        }
    }

    /**
     * Determine whether the entry passes validation.
     *
     * @return bool `true` if the entry is valid, `false` if the underlying file does not exist or the entry name is invalid.
     */
    public function validateLoosely(): bool
    {
        try {
            $this->validate();

            return true;
        } catch (FileNotFoundException|InvalidEntryNameException) {
            return false;
        }
    }
}
