<?php

namespace Modules\Accounting\Providers;

use Illuminate\Support\Facades\Event;
use Illuminate\Support\ServiceProvider;
use Modules\Accounting\Domain\Customers\Services\CustomerQueryService;
use Modules\Accounting\Domain\Payments\Actions\AllocatePaymentAction;
use Modules\Accounting\Domain\Payments\Actions\AutoAllocatePaymentAction;
use Modules\Accounting\Domain\Payments\Actions\CreatePaymentBatchAction;
use Modules\Accounting\Domain\Payments\Actions\ProcessPaymentBatchAction;
use Modules\Accounting\Domain\Payments\Actions\RecordPaymentAction;
use Modules\Accounting\Domain\Payments\Actions\ReverseAllocationAction;
use Modules\Accounting\Domain\Payments\Actions\ReversePaymentAction;
use Modules\Accounting\Domain\Payments\Listeners\PaymentAuditListener;
use Modules\Accounting\Domain\Payments\Services\PaymentQueryService;

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

        $this->mergeConfigFrom(
            __DIR__.'/../Domain/Customers/Actions/registry.php',
            'accounting.customer_actions'
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

        // Register customer actions with the command bus
        $this->app->bind('customer.create', \Modules\Accounting\Domain\Customers\Actions\CreateCustomerAction::class);
        $this->app->bind('customer.update', \Modules\Accounting\Domain\Customers\Actions\UpdateCustomerAction::class);
        $this->app->bind('customer.delete', \Modules\Accounting\Domain\Customers\Actions\DeleteCustomerAction::class);
        $this->app->bind('customer.status', \Modules\Accounting\Domain\Customers\Actions\ChangeCustomerStatusAction::class);

        // Register services
        $this->app->singleton(PaymentQueryService::class);
        $this->app->singleton(CustomerQueryService::class);

        // Load routes
        $this->loadRoutesFrom(__DIR__.'/../routes/api.php');
        $this->loadRoutesFrom(__DIR__.'/../Domain/Customers/routes/api.php');

        // Register event subscribers
        Event::subscribe(PaymentAuditListener::class);

        // Register CLI commands
        if ($this->app->runningInConsole()) {
            $this->commands([
                // Payment commands
                \Modules\Accounting\CLI\Commands\PaymentAllocationReport::class,
                \Modules\Accounting\CLI\Commands\PaymentAllocate::class,
                \Modules\Accounting\CLI\Commands\PaymentAllocationReverse::class,
                \Modules\Accounting\CLI\Commands\PaymentRecord::class,
                \Modules\Accounting\CLI\Commands\PaymentReverse::class,
                
                // Customer commands
                \Modules\Accounting\CLI\Commands\CustomerCreate::class,
                \Modules\Accounting\CLI\Commands\CustomerList::class,
                \Modules\Accounting\CLI\Commands\CustomerUpdate::class,
                \Modules\Accounting\CLI\Commands\CustomerDelete::class,
                \Modules\Accounting\CLI\Commands\CustomerContactAdd::class,
                \Modules\Accounting\CLI\Commands\CustomerContactList::class,
                \Modules\Accounting\CLI\Commands\CustomerAddressAdd::class,
                \Modules\Accounting\CLI\Commands\CustomerAddressList::class,
                \Modules\Accounting\CLI\Commands\CustomerCreditAdjust::class,
                \Modules\Accounting\CLI\Commands\CustomerAgingUpdateCommand::class,
                \Modules\Accounting\CLI\Commands\CustomerCommunicationLog::class,
                \Modules\Accounting\CLI\Commands\CustomerCommunicationList::class,
                
                // Invoice commands
                \Modules\Accounting\CLI\Commands\InvoiceCreate::class,
                
                // System commands
                \Modules\Accounting\CLI\Commands\CompanyList::class,
                \Modules\Accounting\CLI\Commands\CompanySwitch::class,
                \Modules\Accounting\CLI\Commands\UserList::class,
                \Modules\Accounting\CLI\Commands\UserSwitch::class,
                \Modules\Accounting\CLI\Commands\ModuleList::class,
                \Modules\Accounting\CLI\Commands\ModuleEnable::class,
            ]);
        }
    }
}
