<?php

namespace ZipStore\Exceptions;

use Throwable;

class InvalidFilepathException extends ZipStoreException
{
    public function __construct(?string $message = null, int $code = 0, ?Throwable $previous = null)
    {
        $message ??= 'File not found';
        parent::__construct($message, $code, $previous);
    }
}
