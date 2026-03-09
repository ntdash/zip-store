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
     * @param  EntryDetails|string  $identifierOrDetails
     * @return void
     */
    public function __construct(array|string $identifierOrDetails, ?string $entryName = null)
    {
        if (\is_string($identifierOrDetails)) {
            $this->file = new self::$entryFileClass($identifierOrDetails);
            $this->entryName = $entryName ?? $this->file->getFilename();

        } else {
            if (! \is_string($identifierOrDetails['identifier'] ?? null)) {
                throw new \InvalidArgumentException('wrong or missing identifier argument');
            }

            $this->file = new self::$entryFileClass($identifierOrDetails['identifier']);
            $this->entryName = $identifierOrDetails['entryName'] ?? $this->file->getFilename();
        }
    }

    /**
     * @param  null|string|EntryDetails  $identifier
     */
    public function clone(null|string|array $identifier = null, ?string $entryName = null): self
    {

        return new self($identifier ?? $this->file->getIdentifier(), $entryName ?? $this->entryName);
    }

    /**
     * @param  null|class-string  $abstract
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
     * @throws FileNotFoundException
     * @throws InvalidEntryNameException
     */
    public function validate(): void
    {
        if (false === $this->file->exists()) {
            throw new FileNotFoundException;
        }

        if (false !== \strpos($this->entryName, '/')) {
            throw new InvalidEntryNameException;
        }
    }

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
