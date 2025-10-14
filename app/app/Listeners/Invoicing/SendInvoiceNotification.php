<?php

namespace App\Listeners\Invoicing;

use App\Events\Invoicing\InvoiceSent;
use App\Models\User;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification as NotificationFacade;

class SendInvoiceNotification implements ShouldQueue
{
    /**
     * Create the event listener.
     */
    public function __construct()
    {
        //
    }

    /**
     * Handle the event.
     */
    public function handle(InvoiceSent $event): void
    {
        $invoice = $event->invoice;

        try {
            // Get customer's primary contact
            $contact = $invoice->customer->primaryContact;

            if ($contact && $contact->email) {
                // Here you would send the actual invoice notification
                // For now, we'll just log it

                Log::info('Invoice notification would be sent', [
                    'invoice_id' => $invoice->invoice_id,
                    'customer_id' => $invoice->customer_id,
                    'contact_email' => $contact->email,
                    'invoice_number' => $invoice->invoice_number,
                    'amount' => $invoice->total_amount,
                ]);

                // TODO: Create and send actual notification
                // Example:
                // $notification = new InvoiceSentNotification($invoice);
                // NotificationFacade::route('mail', $contact->email)->notify($notification);
            }

            // Also notify company users who should be alerted
            $companyUsers = User::whereHas('companies', function ($query) use ($invoice) {
                $query->where('auth.companies.id', $invoice->company_id);
            })->get();

            foreach ($companyUsers as $user) {
                Log::info('Company user would be notified of sent invoice', [
                    'user_id' => $user->id,
                    'invoice_id' => $invoice->invoice_id,
                    'invoice_number' => $invoice->invoice_number,
                ]);

                // TODO: Create and send internal notification
                // Example:
                // $user->notify(new InvoiceSentInternalNotification($invoice));
            }

        } catch (\Exception $e) {
            Log::error('Failed to send invoice notification', [
                'invoice_id' => $invoice->invoice_id,
                'error' => $e->getMessage(),
            ]);

            throw $e;
        }
    }
}
