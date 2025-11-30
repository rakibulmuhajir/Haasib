<?php

namespace App\Exceptions;

class CommandNotFoundException extends \Exception
{
    public function __construct(string $action)
    {
        parent::__construct("Unknown command: {$action}");
    }
}
