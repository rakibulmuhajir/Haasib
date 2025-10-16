<?php

namespace Modules\Accounting\Providers;

use Illuminate\Support\Facades\Event;
use Illuminate\Support\ServiceProvider;
use Modules\Accounting\Domain\Customers\Services\CustomerQueryService;
use Modules\Accounting\Domain\JournalEntries\Events\BatchApproved;
use Modules\Accounting\Domain\JournalEntries\Events\BatchCreated;
use Modules\Accounting\Domain\JournalEntries\Events\BatchDeleted;
use Modules\Accounting\Domain\JournalEntries\Events\BatchPosted;
use Modules\Accounting\Domain\JournalEntries\Events\EntryAddedToBatch;
use Modules\Accounting\Domain\JournalEntries\Events\EntryRemovedFromBatch;
use Modules\Accounting\Domain\JournalEntries\Listeners\LogBatchApproved;
use Modules\Accounting\Domain\JournalEntries\Listeners\LogBatchCreated;
use Modules\Accounting\Domain\JournalEntries\Listeners\LogBatchDeleted;
use Modules\Accounting\Domain\JournalEntries\Listeners\ProcessBatchPosted;
use Modules\Accounting\Domain\Ledgers\Actions\ApproveJournalEntryAction;
use Modules\Accounting\Domain\Ledgers\Actions\AutoJournalEntryAction;
use Modules\Accounting\Domain\Ledgers\Actions\CreateManualJournalEntryAction;
use Modules\Accounting\Domain\Ledgers\Actions\PostJournalEntryAction;
use Modules\Accounting\Domain\Ledgers\Actions\ReverseJournalEntryAction;
use Modules\Accounting\Domain\Ledgers\Actions\SubmitJournalEntryAction;
use Modules\Accounting\Domain\Ledgers\Actions\VoidJournalEntryAction;
use Modules\Accounting\Domain\Ledgers\Listeners\InvoicePostedSubscriber;
use Modules\Accounting\Domain\Ledgers\Listeners\JournalAuditSubscriber;
use Modules\Accounting\Domain\Ledgers\Services\JournalQueryService;
use Modules\Accounting\Domain\Ledgers\Services\LedgerService;
use Modules\Accounting\Domain\Payments\Actions\AllocatePaymentAction;
use Modules\Accounting\Domain\Payments\Actions\AutoAllocatePaymentAction;
use Modules\Accounting\Domain\Payments\Actions\CreatePaymentBatchAction;
use Modules\Accounting\Domain\Payments\Actions\ProcessPaymentBatchAction;
use Modules\Accounting\Domain\Payments\Actions\RecordPaymentAction;
use Modules\Accounting\Domain\Payments\Actions\ReverseAllocationAction;
use Modules\Accounting\Domain\Payments\Actions\ReversePaymentAction;
use Modules\Accounting\Domain\Payments\Listeners\PaymentAuditListener;
use Modules\Accounting\Domain\Payments\Services\LedgerService as PaymentLedgerService;
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

        $this->mergeConfigFrom(
            __DIR__.'/../Domain/Ledgers/Actions/registry.php',
            'accounting.journal_actions'
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

        // Register journal actions with the command bus
        $this->app->bind('journal.create', CreateManualJournalEntryAction::class);
        $this->app->bind('journal.submit', SubmitJournalEntryAction::class);
        $this->app->bind('journal.approve', ApproveJournalEntryAction::class);
        $this->app->bind('journal.post', PostJournalEntryAction::class);
        $this->app->bind('journal.reverse', ReverseJournalEntryAction::class);
        $this->app->bind('journal.void', VoidJournalEntryAction::class);
        $this->app->bind('journal.auto', AutoJournalEntryAction::class);

        // Register services
        $this->app->singleton(PaymentQueryService::class);
        $this->app->singleton(CustomerQueryService::class);
        $this->app->singleton(JournalQueryService::class);
        $this->app->singleton(LedgerService::class);
        $this->app->singleton(PaymentLedgerService::class);

        // Load routes
        $this->loadRoutesFrom(__DIR__.'/../routes/api.php');
        $this->loadRoutesFrom(__DIR__.'/../Domain/Customers/routes/api.php');
        $this->loadRoutesFrom(__DIR__.'/../Domain/Ledgers/routes/api.php');

        // Register event subscribers
        Event::subscribe(PaymentAuditListener::class);
        Event::subscribe(JournalAuditSubscriber::class);
        Event::subscribe(InvoicePostedSubscriber::class);

        // Register payment event listeners for automatic journaling
        Event::listen(
            \Modules\Accounting\Domain\Payments\Events\PaymentRecorded::class,
            [PaymentLedgerService::class, 'recordPayment']
        );

        Event::listen(
            \Modules\Accounting\Domain\Payments\Events\PaymentAllocated::class,
            [PaymentLedgerService::class, 'recordAllocation']
        );

        Event::listen(
            \Modules\Accounting\Domain\Payments\Events\PaymentReversed::class,
            [PaymentLedgerService::class, 'recordPaymentReversal']
        );

        Event::listen(
            \Modules\Accounting\Domain\Payments\Events\AllocationReversed::class,
            [PaymentLedgerService::class, 'recordAllocationReversal']
        );

        // Register batch lifecycle event listeners
        Event::listen(
            BatchCreated::class,
            LogBatchCreated::class
        );

        Event::listen(
            BatchApproved::class,
            LogBatchApproved::class
        );

        Event::listen(
            BatchPosted::class,
            ProcessBatchPosted::class
        );

        Event::listen(
            BatchDeleted::class,
            LogBatchDeleted::class
        );

        // Queue batch lifecycle events for async processing
        Event::listen([
            BatchCreated::class,
            BatchApproved::class,
            BatchPosted::class,
            BatchDeleted::class,
            EntryAddedToBatch::class,
            EntryRemovedFromBatch::class,
        ], function ($event) {
            dispatch(new \Modules\Accounting\Jobs\ProcessBatchLifecycleEvents($event));
        });

        // Register CLI commands
        if ($this->app->runningInConsole()) {
            $this->commands([
                // Customer commands
                \Modules\Accounting\CLI\Commands\CustomerAgingUpdateCommand::class,

                // Recurring journal template commands
                \Modules\Accounting\CLI\Commands\JournalTemplateCreateCommand::class,
                \Modules\Accounting\CLI\Commands\JournalTemplateListCommand::class,
                \Modules\Accounting\CLI\Commands\JournalTemplateDeactivateCommand::class,
                \Modules\Accounting\CLI\Commands\JournalTemplateGenerateCommand::class,

                // Recurring journal generation job
                \Modules\Accounting\Console\Commands\GenerateRecurringJournalEntriesCommand::class,
            ]);
        }
    }
}
