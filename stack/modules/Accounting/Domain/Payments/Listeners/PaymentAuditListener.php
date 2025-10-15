<?php

namespace Modules\Accounting\Domain\Payments\Listeners;

use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Modules\Accounting\Domain\Payments\Events\PaymentAudited;
use Modules\Accounting\Domain\Payments\Events\AllocationReversed;
use Modules\Accounting\Domain\Payments\Events\BankReconciliationMarker;

class PaymentAuditListener implements ShouldQueue
{
    use InteractsWithQueue;

    /**
     * Handle payment audit events.
     */
    public function handlePaymentAudited(PaymentAudited $event): void
    {
        $this->storeAuditEntry($event->data);
    }

    /**
     * Handle allocation reversal events.
     */
    public function handleAllocationReversed(AllocationReversed $event): void
    {
        // Convert allocation reversal to audit format
        $auditData = [
            'payment_id' => $event->data['payment_id'],
            'company_id' => $event->data['company_id'],
            'actor_id' => $event->data['actor_id'],
            'actor_type' => $event->data['actor_type'],
            'action' => $event->data['action'],
            'timestamp' => $event->data['timestamp'],
            'metadata' => json_encode($event->data['metadata']),
        ];

        $this->storeAuditEntry($auditData);
    }

    /**
     * Handle bank reconciliation events.
     */
    public function handleBankReconciliationMarker(BankReconciliationMarker $event): void
    {
        // Convert reconciliation event to audit format
        $auditData = [
            'payment_id' => $event->data['payment_id'],
            'company_id' => $event->data['company_id'],
            'actor_id' => $event->data['actor_id'],
            'actor_type' => $event->data['actor_type'],
            'action' => 'bank_reconciled',
            'timestamp' => $event->data['timestamp'],
            'metadata' => json_encode($event->data['metadata']),
        ];

        $this->storeAuditEntry($auditData);
    }

    /**
     * Store audit entry in the database.
     */
    private function storeAuditEntry(array $data): void
    {
        try {
            DB::table('invoicing.payment_audit_log')->insert([
                'id' => Str::uuid(),
                'payment_id' => $data['payment_id'],
                'company_id' => $data['company_id'],
                'action' => $data['action'],
                'actor_id' => $data['actor_id'],
                'actor_type' => $data['actor_type'],
                'timestamp' => $data['timestamp'],
                'metadata' => $data['metadata'],
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        } catch (\Exception $e) {
            // Log the error but don't fail the request
            \Log::error('Failed to store payment audit entry', [
                'error' => $e->getMessage(),
                'data' => $data,
            ]);
        }
    }

    /**
     * Register the listeners for the subscriber.
     */
    public function subscribe($events)
    {
        $events->listen(
            PaymentAudited::class,
            [PaymentAuditListener::class, 'handlePaymentAudited']
        );

        $events->listen(
            AllocationReversed::class,
            [PaymentAuditListener::class, 'handleAllocationReversed']
        );

        $events->listen(
            BankReconciliationMarker::class,
            [PaymentAuditListener::class, 'handleBankReconciliationMarker']
        );
    }
}