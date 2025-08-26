<?php

// app/Console/Commands/CompanyUnassign.php
namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Support\DevOpsService;

class CompanyUnassign extends Command
{
    protected $signature = 'company:unassign {--email=} {--company=}';
    protected $description = 'Remove user from company';

    public function handle(DevOpsService $ops): int
    {
        $out = $ops->unassignCompany($this->option('email') ?? '', $this->option('company') ?? '');
        $this->line(json_encode(['ok' => true, 'output' => $out], JSON_PRETTY_PRINT));
        return self::SUCCESS;
    }
}

