<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;

class MigrateFreshSchemas extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'migrate:fresh-schemas {--seed}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Drop all schemas and run fresh migrations';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('Dropping all schemas...');

        // Drop all custom schemas
        DB::statement('DROP SCHEMA IF EXISTS auth CASCADE;');
        DB::statement('DROP SCHEMA IF EXISTS hrm CASCADE;');
        DB::statement('DROP SCHEMA IF EXISTS acct CASCADE;');

        // Drop and recreate public schema
        DB::statement('DROP SCHEMA public CASCADE; CREATE SCHEMA public;');

        $this->info('Running fresh migrations...');

        // Run migrations
        Artisan::call('migrate', [], $this->getOutput());

        // Run seeders if requested
        if ($this->option('seed')) {
            $this->info('Running seeders...');
            Artisan::call('db:seed', [], $this->getOutput());
        }

        $this->info('Migration complete!');

        return Command::SUCCESS;
    }
}
