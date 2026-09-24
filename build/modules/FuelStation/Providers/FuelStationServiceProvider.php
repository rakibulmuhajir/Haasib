<?php

namespace App\Modules\FuelStation\Providers;

use App\Modules\Accounting\Models\Transaction;
use App\Modules\Accounting\Services\DocumentDateLock;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;

class FuelStationServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $this->loadMigrationsFrom(__DIR__ . '/../Database/Migrations');

        Route::middleware('web')
            ->group(__DIR__ . '/../Routes/fuel.php');

        // A bill (or any other document) dated on a day whose daily close has
        // been locked can't be edited without unlocking the day first -- see
        // DailyCloseLockService. Reversed closes never lock anything: a
        // reversal supersedes the original, so its date is open again until a
        // fresh close for it is posted and locked.
        DocumentDateLock::extend(function (string $companyId, string $date): ?string {
            $locked = Transaction::where('company_id', $companyId)
                ->where('transaction_type', 'fuel_daily_close')
                ->whereDate('transaction_date', $date)
                ->whereNull('deleted_at')
                ->whereNull('reversed_by_id')
                ->where('is_locked', true)
                ->exists();

            return $locked ? "{$date} is locked by its daily close. Unlock the day to edit." : null;
        });
    }
}
