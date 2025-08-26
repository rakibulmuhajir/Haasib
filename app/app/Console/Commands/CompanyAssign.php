<?php

// app/Console/Commands/CompanyAssign.php
namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Support\DevOpsService;

class CompanyAssign extends Command
{
    protected $signature = 'haasib:company:assign {--email=} {--company=} {--role=viewer}';
    protected $description = 'Assign user to company with role';

    public function handle(DevOpsService $ops): int
    {
        $out = $ops->assignCompany($this->option('email') ?? '', $this->option('company') ?? '', $this->option('role') ?? 'viewer');
        $this->line(json_encode(['ok' => true, 'output' => $out], JSON_PRETTY_PRINT));
        return self::SUCCESS;
    }
}
