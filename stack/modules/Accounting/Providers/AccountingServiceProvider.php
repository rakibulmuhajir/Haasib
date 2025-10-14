<?php

namespace Modules\Accounting\Providers;

use Illuminate\Support\ServiceProvider;
use Modules\Accounting\Domain\Payments\Actions\RecordPaymentAction;
use Modules\Accounting\Domain\Payments\Actions\AllocatePaymentAction;
use Modules\Accounting\Domain\Payments\Actions\AutoAllocatePaymentAction;
use Modules\Accounting\Domain\Payments\Actions\ReversePaymentAction;
use Modules\Accounting\Domain\Payments\Actions\ReverseAllocationAction;
use Modules\Accounting\Domain\Payments\Actions\CreatePaymentBatchAction;
use Modules\Accounting\Domain\Payments\Actions\ProcessPaymentBatchAction;

class AccountingServiceProvider extends ServiceProvider
{
    /**
     * Register services.
     */
    public function register(): void
    {
        $this->mergeConfigFrom(
            __DIR__.'/../Domain/Payments/Actions/registry.php',
            'accounting.actions'
        );
    }

    /**
     * Bootstrap services.
     */
    public function boot(): void
    {
        // Register payment actions with the command bus
        $this->app->bind('payment.create', RecordPaymentAction::class);
        $this->app->bind('payment.allocate', AllocatePaymentAction::class);
        $this->app->bind('payment.allocate.auto', AutoAllocatePaymentAction::class);
        $this->app->bind('payment.reverse', ReversePaymentAction::class);
        $this->app->bind('payment.allocation.reverse', ReverseAllocationAction::class);
        $this->app->bind('payment.batch.create', CreatePaymentBatchAction::class);
        $this->app->bind('payment.batch.process', ProcessPaymentBatchAction::class);
    }
}