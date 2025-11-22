<?php

namespace Modules\Accounting\CLI\Commands;

use Illuminate\Console\Command;

class AcctCommand extends Command
{
    /**
     * The name and signature of the console command.
     */
    protected $signature = 'acct:example';

    /**
     * The console command description.
     */
    protected $description = 'Example command for Acct module';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $this->info('Acct module is working!');
        return Command::SUCCESS;
    }
}
