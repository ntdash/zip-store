<?php

namespace Tests\Exceptions;

use Exception;
use Tests\Support\Table;

class DBRowNotFoundException extends Exception
{
    public function __construct(int $id, Table $table)
    {
        $message = sprintf('Tabe:%s row with id:%s not found', $table->getTable(), $id);
        parent::__construct($message);
    }
}
