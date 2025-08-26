<?php

// app/Console/Commands/UserDelete.php
namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Support\DevOpsService;

class UserDelete extends Command
{
    protected $signature = 'user:delete {--email=}';
    protected $description = 'Delete a user by email';

    public function handle(DevOpsService $ops): int
    {
        $out = $ops->deleteUser($this->option('email') ?? '');
        $this->line(json_encode(['ok' => true, 'output' => $out], JSON_PRETTY_PRINT));
        return self::SUCCESS;
    }
}

