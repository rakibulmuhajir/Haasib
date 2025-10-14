<?php

// app/Console/Commands/BootstrapDemo.php
namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Support\DevOpsService;

class BootstrapDemo extends Command
{
    protected $signature = 'bootstrap:demo {--name=Founder} {--email=} {--companies=} {--role=owner}';
    protected $description = 'Create a user and attach companies list';

    public function handle(DevOpsService $ops): int
    {
        $companies = array_filter(array_map('trim', explode(',', (string) $this->option('companies'))));
        $out = $ops->createUserAndCompanies(
            (string) $this->option('name'),
            (string) $this->option('email'),
            $companies,
            (string) $this->option('role')
        );
        $this->line(json_encode(['ok' => true, 'output' => $out], JSON_PRETTY_PRINT));
        return self::SUCCESS;
    }
}

