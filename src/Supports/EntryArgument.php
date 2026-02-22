<?php

namespace ZipStore\Supports;

use ZipStore\Exceptions\InvalidEntryNameException;
use ZipStore\Exceptions\InvalidFilepathException;

/**
 * @phpstan-type EntryDetails array{entryName?:string,filepath?:string}
 */
class EntryArgument
{
    public readonly string $entryName;

    public readonly string $filepath;

    /**
     * @param  EntryDetails|string $filepathOrDetails
     * @return void
     */
    public function __construct(array|string $filepathOrDetails, ?string $entryName = null)
    {
        if (\is_string($filepathOrDetails)) {
            $this->filepath = $filepathOrDetails;
            $this->entryName = $entryName ?? \basename($this->filepath);

        } else {
            if (! \is_string($filepathOrDetails['filepath'] ?? null)) {
                throw new InvalidFilepathException('wrong or missing filepath argument');
            }

            $this->filepath = $filepathOrDetails['filepath'];
            $this->entryName = $filepathOrDetails['entryName'] ?? \basename($this->filepath);

        }
    }

    /**
     *
     * @param null|string|EntryDetails $filepath
     * @param null|string $entryName
     * @return EntryArgument
     */
    public function clone(null|string|array $filepath = null, ?string $entryName = null): self
    {
        return new self($filepath ?? $this->filepath, $entryName ?? $this->entryName);
    }

    /**
     * @throws InvalidFilepathException
     * @throws InvalidEntryNameException
     */
    public function validate(): void
    {
        if (false === \is_file($this->filepath)) {
            throw new InvalidFilepathException;
        }

        if (false !== \strpos($this->entryName, DIRECTORY_SEPARATOR)) {
            throw new InvalidEntryNameException;
        }
    }

    public function validateLoosely(): bool
    {
        try {
            $this->validate();

            return true;
        } catch (InvalidFilepathException|InvalidEntryNameException) {
            return false;
        }
    }

}
