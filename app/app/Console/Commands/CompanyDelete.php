<?php

// app/Console/Commands/CompanyDelete.php
namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Support\DevOpsService;

class CompanyDelete extends Command
{
    protected $signature = 'haasib:company:delete {--company=}';
    protected $description = 'Delete a company by id or name';

    public function handle(DevOpsService $ops): int
    {
        $out = $ops->deleteCompany($this->option('company') ?? '');
        $this->line(json_encode(['ok' => true, 'output' => $out], JSON_PRETTY_PRINT));
        return self::SUCCESS;
    }
}

