<?php

namespace App\Jobs\AccountsReceivable;

use App\Models\AccountsReceivable;
use App\Models\Invoice;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class UpdateForCustomer implements ShouldBeUnique, ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * The number of seconds the job can run before timing out.
     */
    public int $timeout = 600; // 10 minutes

    /**
     * The number of times the job may be attempted.
     */
    public int $tries = 3;

    /**
     * The number of seconds to wait before retrying the job.
     */
    public array $backoff = [60, 300]; // 1 minute, then 5 minutes

    /**
     * The unique ID of the job.
     */
    public function uniqueId(): string
    {
        return 'ar_update_customer_'.$this->customerId;
    }

    /**
     * Get the tags that should be assigned to the job.
     */
    public function tags(): array
    {
        return ['accounts_receivable', 'customer:'.$this->customerId];
    }

    /**
     * Create a new job instance.
     */
    public function __construct(public string $customerId)
    {
        //
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        Log::info('Starting A/R update job for customer.', ['customer_id' => $this->customerId]);
        $startTime = microtime(true);
        $processedCount = 0;

        Invoice::where('customer_id', $this->customerId)
            ->where('status', '!=', 'cancelled')
            ->chunkById(100, function ($invoices) use (&$processedCount) {
                foreach ($invoices as $invoice) {
                    $ar = AccountsReceivable::firstOrNew(['invoice_id' => $invoice->id]);

                    if (! $ar->exists) {
                        $ar->ar_id = (string) Str::uuid();
                        $ar->company_id = $invoice->company_id;
                        $ar->customer_id = $invoice->customer_id;
                        $ar->currency_id = $invoice->currency_id;
                    }

                    $ar->updateFromInvoice();
                    $processedCount++;
                }
            });

        $duration = round(microtime(true) - $startTime, 2);
        Log::info("Finished A/R update job for customer. Processed {$processedCount} invoices in {$duration} seconds.", ['customer_id' => $this->customerId]);
    }
}
