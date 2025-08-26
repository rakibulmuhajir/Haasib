<?php

// app/Console/Commands/CompanyAdd.php
namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Support\DevOpsService;

class CompanyAdd extends Command
{
    protected $signature = 'company:add {--name=}';
    protected $description = 'Add company (idempotent by name)';

    public function handle(DevOpsService $ops): int
    {
        $out = $ops->createCompany($this->option('name') ?? '');
        $this->line(json_encode(['ok' => true, 'output' => $out], JSON_PRETTY_PRINT));
        return self::SUCCESS;
    }
}
