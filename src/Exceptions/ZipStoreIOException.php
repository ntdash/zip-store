<?php

namespace ZipStore\Exceptions;

use Exception;

class ZipStoreIOException extends Exception
{
    public function __construct(string $message = '')
    {
        parent::__construct($message);
    }
}
