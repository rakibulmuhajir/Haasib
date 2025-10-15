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

                // Send invoice notification via email
                try {
                    \Illuminate\Support\Facades\Mail::to($contact->email)
                        ->send(new \App\Mail\InvoiceSentMail($invoice));
                        
                    Log::info('Invoice notification sent', [
                        'invoice_id' => $invoice->id,
                        'contact_email' => $contact->email,
                        'invoice_number' => $invoice->invoice_number,
                    ]);
                } catch (\Exception $e) {
                    Log::error('Failed to send invoice notification', [
                        'invoice_id' => $invoice->id,
                        'contact_email' => $contact->email,
                        'error' => $e->getMessage(),
                    ]);
                }
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

                // Send internal notification to company users
                try {
                    $user->notify(new \App\Notifications\InvoiceSentInternalNotification($invoice));
                    
                    Log::info('Internal invoice notification sent', [
                        'user_id' => $user->id,
                        'invoice_id' => $invoice->id,
                        'invoice_number' => $invoice->invoice_number,
                    ]);
                } catch (\Exception $e) {
                    Log::error('Failed to send internal invoice notification', [
                        'user_id' => $user->id,
                        'invoice_id' => $invoice->id,
                        'error' => $e->getMessage(),
                    ]);
                }
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
