<?php

namespace App\Providers;

use App\Events\Invoicing\InvoiceCancelled;
use App\Events\Invoicing\InvoicePaid;
use App\Events\Invoicing\InvoicePosted;
use App\Events\Invoicing\InvoiceSent;
use App\Events\Ledger\JournalEntryCancelled;
use App\Events\Ledger\JournalEntryPosted;
use App\Events\Ledger\JournalEntryVoided;
use App\Listeners\Invoicing\SendInvoiceNotification;
use App\Listeners\Invoicing\UpdateAccountsReceivableForCancelledInvoice;
use App\Listeners\Invoicing\UpdateAccountsReceivableForPaidInvoice;
use App\Listeners\Invoicing\UpdateAccountsReceivableForPostedInvoice;
use App\Listeners\Ledger\VoidJournalEntryForCancelledInvoice;
use App\Listeners\Ledger\CreateJournalEntryForPostedInvoice;
use App\Listeners\Ledger\ReverseLedgerForVoidedJournalEntry;
use App\Listeners\Ledger\UpdateLedgerForPostedJournalEntry;
use Illuminate\Auth\Events\Registered;
use Illuminate\Auth\Listeners\SendEmailVerificationNotification;
use Illuminate\Foundation\Support\Providers\EventServiceProvider as ServiceProvider;

class EventServiceProvider extends ServiceProvider
{
    /**
     * The event to listener mappings for the application.
     *
     * @var array<class-string, array<int, class-string>>
     */
    protected $listen = [
        Registered::class => [
            SendEmailVerificationNotification::class,
        ],
        // Invoice events
        InvoiceSent::class => [
            SendInvoiceNotification::class,
        ],
        InvoicePosted::class => [
            CreateJournalEntryForPostedInvoice::class,
            UpdateAccountsReceivableForPostedInvoice::class,
        ],
        InvoicePaid::class => [
            UpdateAccountsReceivableForPaidInvoice::class,
        ],
        InvoiceCancelled::class => [
            UpdateAccountsReceivableForCancelledInvoice::class,
            VoidJournalEntryForCancelledInvoice::class,
        ],
        // Journal entry events
        JournalEntryPosted::class => [
            UpdateLedgerForPostedJournalEntry::class,
        ],
        JournalEntryVoided::class => [
            ReverseLedgerForVoidedJournalEntry::class,
        ],
        JournalEntryCancelled::class => [
            // Add listeners for cancelled journal entries if needed
        ],
    ];

    /**
     * Register any events for your application.
     */
    public function boot(): void
    {
        //
    }

    /**
     * Determine if events and listeners should be automatically discovered.
     */
    public function shouldDiscoverEvents(): bool
    {
        return false;
    }
}
