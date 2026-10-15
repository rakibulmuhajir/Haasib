<?php

namespace App\Modules\FuelStation\Providers;

use App\Modules\Accounting\Events\InvoiceVoided;
use App\Modules\Accounting\Extensions\BillDefaults;
use App\Modules\Accounting\Extensions\CustomerPages;
use App\Modules\Accounting\Extensions\InvoiceMutationGuard;
use App\Modules\Accounting\Models\Account;
use App\Modules\Accounting\Models\Invoice;
use App\Modules\Accounting\Models\Transaction;
use App\Modules\Accounting\Services\DocumentDateLock;
use App\Modules\Accounting\Services\SalesDiscountAccountResolver;
use App\Modules\FuelStation\Listeners\ReverseTemporaryFuelStock;
use App\Modules\FuelStation\Models\SaleMetadata;
use App\Modules\FuelStation\Models\StationSettings;
use App\Modules\FuelStation\Services\DailyCloseCreditSaleService;
use App\Modules\FuelStation\Services\TemporaryFuelStockService;
use App\Modules\Inventory\Models\StockMovement;
use App\Modules\Inventory\Models\Warehouse;
use Illuminate\Support\Facades\Event;
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
        $this->app->make(\App\Support\Modules\ModuleRegistry::class)->register(new \App\Modules\FuelStation\FuelStationModule);

        $this->loadMigrationsFrom(__DIR__.'/../Database/Migrations');

        Route::middleware('web')
            ->group(__DIR__.'/../Routes/fuel.php');

        SalesDiscountAccountResolver::extend('fuel_station', fn (string $companyId): ?string => StationSettings::where('company_id', $companyId)->value('sales_discount_account_id'));

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

        // A card channel set to "Settles to: Supplier" pays the supplier from its clearing
        // account at the daily close; tell the core which clearing accounts those are.
        \App\Modules\Accounting\Services\ClearingPaymentAccounts::extend(function (string $companyId, string $accountId): bool {
            $channels = \App\Modules\FuelStation\Models\StationSettings::where('company_id', $companyId)->value('payment_channels');
            $channels = is_string($channels) ? (json_decode($channels, true) ?: []) : ($channels ?? []);

            return collect($channels)->contains(fn ($channel) => ($channel['settles_to'] ?? null) === 'supplier'
                && ($channel['clearing_account_id'] ?? null) === $accountId);
        });

        // Invoice guard: assertMutable ran for every company before the hooks existed, so it
        // stays ungated (a no-op for invoices no daily close references).
        InvoiceMutationGuard::extend('fuel_station', function (Invoice $invoice, string $operation): void {
            app(DailyCloseCreditSaleService::class)->assertMutable($invoice);

            if ($operation === 'update' && StockMovement::where('company_id', $invoice->company_id)
                ->where('reference_type', 'acct.invoices')->where('reference_id', $invoice->id)->where('movement_type', 'sale')
                ->whereIn('warehouse_id', Warehouse::where('company_id', $invoice->company_id)->where('code', TemporaryFuelStockService::CODE)->select('id'))
                ->exists()) {
                throw new \RuntimeException('This sale issued temporary fuel stock. Cancel it and record the corrected sale so quantity and cost stay together.');
            }
        });

        // Event::listen is not keyed; attach once per app instance.
        if (! Event::hasListeners(InvoiceVoided::class)) {
            Event::listen(InvoiceVoided::class, ReverseTemporaryFuelStock::class);
        }

        BillDefaults::extend('fuel_station', function ($company): ?string {
            if (! $company->hasModule('fuel_station')) {
                return null;
            }

            return Account::where('company_id', $company->id)->where('code', '1200')->where('is_active', true)->value('id')
                ?? Account::where('company_id', $company->id)->where('subtype', 'inventory')->where('is_active', true)->orderBy('code')->value('id');
        });

        CustomerPages::extend('fuel_station', fn ($company, $customer): ?string => $company->hasModule('fuel_station')
            ? route('fuel.credit-customers.show', ['company' => $company->slug, 'customer' => $customer->id])
            : null);

        Invoice::resolveRelationUsing('saleMetadata', fn (Invoice $invoice) => $invoice->hasOne(SaleMetadata::class, 'invoice_id'));
    }
}
