<?php

// app/Console/Commands/UserAdd.php
namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Support\DevOpsService;

class UserAdd extends Command
{
    protected $signature = 'user:add {--name=} {--email=} {--password=}';
    protected $description = 'Add user (idempotent by email)';

    public function handle(DevOpsService $ops): int
    {
        $out = $ops->createUser($this->option('name') ?? 'User', $this->option('email') ?? '', $this->option('password'));
        $this->line(json_encode(['ok' => true, 'output' => $out], JSON_PRETTY_PRINT));
        return self::SUCCESS;
    }
}

