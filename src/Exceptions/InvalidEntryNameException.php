<?php

namespace ZipStore\Exceptions;

use Throwable;

class InvalidEntryNameException extends ZipStoreException
{
    public function __construct(?string $message = null, int $code = 0, ?Throwable $previous = null)
    {
        $message ??= 'entryName should not countain directory separator';
        parent::__construct($message, $code, $previous);
    }
}
