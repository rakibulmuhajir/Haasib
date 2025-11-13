<?php

namespace App\Providers;

use App\Models\Account;
use App\Models\Bill;
use App\Models\Company;
use App\Models\Customer;
use App\Models\Expense;
use App\Models\Invoice;
use App\Models\JournalEntry;
use App\Models\Payment;
use App\Models\Acct\PurchaseOrder;
use App\Models\User;
use App\Models\Vendor;
use App\Observers\AuditObserver;
use Illuminate\Auth\Events\Registered;
use Illuminate\Auth\Listeners\SendEmailVerificationNotification;
use Illuminate\Foundation\Support\Providers\EventServiceProvider as ServiceProvider;

class EventServiceProvider extends ServiceProvider
{
    protected $listen = [
        Registered::class => [
            SendEmailVerificationNotification::class,
        ],
    ];

    public function boot(): void
    {
        // Register audit observers for key models
        $this->observeModels([
            // User and security models
            User::class,
            Company::class,

            // Financial models
            Invoice::class,
            Payment::class,
            Bill::class,
            Expense::class,
            JournalEntry::class,
            Account::class,

            // Business entity models
            Customer::class,
            Vendor::class,
            PurchaseOrder::class,
        ]);
    }

    /**
     * Register audit observer for multiple models.
     */
    protected function observeModels(array $models): void
    {
        foreach ($models as $model) {
            if (class_exists($model)) {
                $model::observe(AuditObserver::class);
            }
        }
    }

    public function shouldDiscoverEvents(): bool
    {
        return false;
    }
}
