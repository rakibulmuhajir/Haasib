<?php

namespace App\StateMachines;

use App\Models\Invoice;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

class InvoiceStateMachine
{
    protected Invoice $invoice;

    /**
     * Events that should be dispatched for each transition.
     */
    protected array $dispatchesEvents = [
        'sent' => \App\Events\Invoicing\InvoiceSent::class,
        'posted' => \App\Events\Invoicing\InvoicePosted::class,
        'paid' => \App\Events\Invoicing\InvoicePaid::class,
        'cancelled' => \App\Events\Invoicing\InvoiceCancelled::class,
    ];

    /**
     * A map of all valid state transitions.
     * Key: current status, Value: array of possible next statuses.
     */
    protected array $transitions = [
        'draft' => ['sent', 'cancelled'],
        'sent' => ['draft', 'posted', 'cancelled', 'partial', 'paid'],
        'posted' => ['sent', 'cancelled', 'partial', 'paid'],
        'partial' => ['paid', 'posted', 'sent'], // Can revert if a payment is voided
        'paid' => ['posted', 'sent'], // Can revert if a payment is voided
        'cancelled' => ['draft'],
    ];

    public function __construct(Invoice $invoice)
    {
        $this->invoice = $invoice;
    }

    public function canTransitionTo(string $newStatus): bool
    {
        return in_array($newStatus, $this->transitions[$this->invoice->status] ?? []);
    }

    /**
     * The main entry point for all status changes.
     *
     * @throws \InvalidArgumentException
     */
    public function transitionTo(string $newStatus, array $context = []): void
    {
        if ($this->invoice->status === $newStatus) {
            return; // No transition needed
        }

        if (! $this->canTransitionTo($newStatus)) {
            throw new \InvalidArgumentException("Cannot transition invoice from [{$this->invoice->status}] to [{$newStatus}].");
        }

        // Run specific validation guards before transitioning
        $this->validateTransition($newStatus, $context);

        $oldStatus = $this->invoice->status;

        // Perform the state change
        $this->invoice->status = $newStatus;

        // Apply side effects of the new state
        $this->applyStateSideEffects($newStatus, $context);

        $this->logStatusTransition($oldStatus, $newStatus, $context['reason'] ?? null);

        $this->invoice->save();

        // Dispatch event if defined
        if (isset($this->dispatchesEvents[$newStatus])) {
            event(new $this->dispatchesEvents[$newStatus]($this->invoice, $context));
        }
    }

    /**
     * Validates specific business rules before a transition is allowed.
     *
     * @throws \InvalidArgumentException
     */
    protected function validateTransition(string $newStatus, array $context): void
    {
        match ($newStatus) {
            'sent' => $this->validateCanBeSent(),
            'posted' => $this->validateCanBePosted(),
            'cancelled' => $this->validateCanBeCancelled($context),
            'draft' => $this->validateCanBeReopened(),
            default => true,
        };
    }

    /**
     * Applies actions and sets metadata based on the new state.
     */
    protected function applyStateSideEffects(string $newStatus, array $context): void
    {
        $metadata = $this->invoice->metadata ?? [];

        switch ($newStatus) {
            case 'sent':
                $this->invoice->sent_at = now();
                break;
            case 'posted':
                $this->invoice->posted_at = now();
                break;
            case 'cancelled':
                $this->invoice->cancelled_at = now();
                $this->invoice->cancelled_by = Auth::id();
                $this->invoice->cancellation_reason = $context['reason'] ?? 'Cancelled without reason.';
                // Keep in metadata for backward compatibility
                $metadata['cancellation_reason'] = $this->invoice->cancellation_reason;
                $metadata['cancelled_at'] = now()->toISOString();
                break;
            case 'draft':
                // When reopening, clear previous state markers
                $this->invoice->sent_at = null;
                $this->invoice->posted_at = null;
                $this->invoice->cancelled_at = null;
                $metadata['reopened_at'] = now()->toISOString();
                $metadata['reopened_by_user_id'] = $context['user_id'] ?? Auth::id();
                break;
        }

        $this->invoice->metadata = $metadata;
    }

    private function validateCanBeSent(): void
    {
        if ($this->invoice->items()->count() === 0) {
            throw new \InvalidArgumentException('Cannot send an invoice with no items.');
        }
        // Allow zero-total invoices to be sent (e.g., complimentary invoices).
        if ($this->invoice->total_amount < 0) {
            throw new \InvalidArgumentException('Cannot send an invoice with a negative total.');
        }
    }

    private function validateCanBePosted(): void
    {
        // Must be transitioning from 'sent' to 'posted'
        if ($this->invoice->status !== 'sent') {
            throw new \InvalidArgumentException('Invoice must be sent before it can be posted.');
        }
        // Must have items and positive total
        if ($this->invoice->items()->count() === 0) {
            throw new \InvalidArgumentException('Cannot post an invoice with no items.');
        }
        if ($this->invoice->total_amount <= 0) {
            throw new \InvalidArgumentException('Cannot post an invoice with a zero or negative total.');
        }
        // Cannot have a future sent_at timestamp
        if (isset($this->invoice->sent_at) && $this->invoice->sent_at->isFuture()) {
            throw new \InvalidArgumentException('Cannot post an invoice that is scheduled to be sent in the future.');
        }
    }

    private function validateCanBeCancelled(array $context): void
    {
        if (empty(trim($context['reason'] ?? ''))) {
            throw new \InvalidArgumentException('A reason is required to cancel an invoice.');
        }
    }

    private function validateCanBeReopened(): void
    {
        if ($this->invoice->paymentAllocations()->where('status', 'active')->exists()) {
            throw new \InvalidArgumentException('Cannot reopen a cancelled invoice that has active payment allocations.');
        }
    }

    /**
     * Updates the invoice status based on its payment status.
     * This is called after a payment is applied or voided.
     */
    public function updatePaymentStatus(): void
    {
        $this->invoice->recalculateAndSave(); // Recalculate totals first

        $oldStatus = $this->invoice->status;
        $newStatus = $oldStatus;

        // Don't automatically change status of draft or cancelled invoices
        if (in_array($oldStatus, ['draft', 'cancelled'])) {
            return;
        }

        if ($this->invoice->isFullyPaid()) {
            $newStatus = 'paid';
        } elseif ($this->invoice->isPartiallyPaid()) {
            $newStatus = 'partial';
        } elseif (in_array($oldStatus, ['paid', 'partial'])) {
            // A payment was likely voided, revert status
            $newStatus = $this->invoice->posted_at ? 'posted' : 'sent';
        }

        if ($newStatus !== $oldStatus && $this->canTransitionTo($newStatus)) {
            $this->transitionTo($newStatus, ['reason' => 'payment_update']);
        }
    }

    private function logStatusTransition(string $oldStatus, string $newStatus, ?string $reason = null): void
    {
        Log::info('Invoice status transition', [
            'invoice_id' => $this->invoice->invoice_id,
            'invoice_number' => $this->invoice->invoice_number,
            'old_status' => $oldStatus,
            'new_status' => $newStatus,
            'reason' => $reason,
            'user_id' => Auth::id(),
            'timestamp' => now()->toISOString(),
        ]);
    }
}
