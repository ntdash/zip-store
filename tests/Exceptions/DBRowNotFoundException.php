<?php

namespace Tests\Exceptions;

use Exception;
use Tests\Support\Table;

class DBRowNotFoundException extends Exception
{
    /**
     * Create an exception representing a missing row in a specific table.
     *
     * The exception message includes the table name and the row id.
     *
     * @param int $id The identifier of the missing row.
     * @param Table $table The table where the row was expected.
     */
    public function __construct(int $id, Table $table)
    {
        $message = sprintf('Tabe:%s row with id:%s not found', $table->getTable(), $id);
        parent::__construct($message);
    }
}
