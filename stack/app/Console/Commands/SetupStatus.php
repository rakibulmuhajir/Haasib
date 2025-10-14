<?php

namespace App\Console\Commands;

use App\Services\SetupService;
use Illuminate\Console\Command;

class SetupStatus extends Command
{
    protected $signature = 'setup:status';

    protected $description = 'Display installation status, user, company, and module counts.';

    public function handle(SetupService $setupService): int
    {
        $status = $setupService->getSystemStatus();

        $this->info('Haasib Platform Status');
        $this->table([
            'Initialized',
            'Users',
            'Companies',
            'Modules',
        ], [[
            $status['is_initialized'] ? 'Yes' : 'No',
            $status['user_count'],
            $status['company_count'],
            $status['module_count'],
        ]]);

        return self::SUCCESS;
    }
}
