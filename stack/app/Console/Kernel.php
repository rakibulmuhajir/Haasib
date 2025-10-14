<?php

namespace App\Console;

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Console\Kernel as ConsoleKernel;

class Kernel extends ConsoleKernel
{
    /**
     * The Artisan commands provided by your application.
     *
     * @var array<int, class-string>
     */
    protected $commands = [];

    /**
     * Define the application's command schedule.
     */
    protected function schedule(Schedule $schedule): void
    {
        //
    }

    /**
     * Register the commands for the application.
     */
    protected function commands(): void
    {
        $this->load(app_path('Console/Commands'));

        $moduleCommandPaths = [
            base_path('modules/Core/CLI/Commands'),
            base_path('modules/Invoicing/CLI/Commands'),
            base_path('modules/Ledger/CLI/Commands'),
        ];

        foreach ($moduleCommandPaths as $path) {
            if (is_dir($path)) {
                $this->load($path);
            }
        }

        require base_path('routes/console.php');
    }
}
