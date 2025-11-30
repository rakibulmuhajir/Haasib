<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class CleanupIdempotencyCommand extends Command
{
    protected $signature = 'palette:cleanup-idempotency';
    protected $description = 'Delete expired idempotency records (older than 24 hours)';

    public function handle(): int
    {
        $deleted = DB::table('command_idempotency')
            ->where('created_at', '<', now()->subHours(24))
            ->delete();

        $this->info("Deleted {$deleted} expired idempotency records");

        return self::SUCCESS;
    }
}
