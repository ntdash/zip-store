<?php

namespace ZipStore\Exceptions;

use Throwable;

class FileNotFoundException extends ZipStoreException
{
    /**
     * Initialize the exception, ensuring a default message when none is provided.
     *
     * If no message is supplied, the message will be set to "File not found".
     *
     * @param string|null $message The exception message or null to use the default.
     * @param int $code The exception code.
     * @param Throwable|null $previous The previous throwable for chaining.
     */
    public function __construct(?string $message = null, int $code = 0, ?Throwable $previous = null)
    {
        $message ??= 'File not found';
        parent::__construct($message, $code, $previous);
    }
}
