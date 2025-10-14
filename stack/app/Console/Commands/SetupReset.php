<?php

namespace App\Console\Commands;

use App\Services\SetupService;
use Illuminate\Console\Command;

class SetupReset extends Command
{
    protected $signature = 'setup:reset {--force : Skip confirmation prompt}';

    protected $description = 'Remove all tenant and user data to return the platform to a pristine state.';

    public function handle(SetupService $setupService): int
    {
        if (! $this->option('force') && ! $this->confirm('This will delete all companies, users, and module assignments. Continue?')) {
            $this->info('Reset aborted.');

            return self::INVALID;
        }

        $setupService->resetSystem();

        $this->info('✅ All tenant data removed. You can now run setup:initialize again.');

        return self::SUCCESS;
    }
}
