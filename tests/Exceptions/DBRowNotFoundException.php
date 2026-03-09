<?php

namespace Tests\Exceptions;

use Exception;
use Tests\Support\Table;

class DBRowNotFoundException extends Exception
{
    public function __construct(int $id, Table $table)
    {
        $message = sprintf('Table:%s row with id:%d not found', $table->getTable(), $id);
        parent::__construct($message);
    }
}
