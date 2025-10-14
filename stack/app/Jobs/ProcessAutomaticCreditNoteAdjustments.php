<?php

namespace App\Jobs;

use App\Models\Company;
use App\Models\User;
use App\Services\CreditNoteService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class ProcessAutomaticCreditNoteAdjustments implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * The number of times the job may be attempted.
     */
    public int $tries = 3;

    /**
     * The maximum number of unhandled exceptions to allow before failing.
     */
    public int $maxExceptions = 3;

    /**
     * Create a new job instance.
     */
    public function __construct(
        private readonly Company $company,
        private readonly ?User $user = null
    ) {
        $this->onQueue('credit-notes');
    }

    /**
     * Execute the job.
     */
    public function handle(CreditNoteService $creditNoteService): void
    {
        $user = $this->user ?? User::where('email', 'system@haasib.app')->first();

        if (! $user) {
            // Create a system user if none exists
            $user = User::create([
                'name' => 'System',
                'email' => 'system@haasib.app',
                'password' => bcrypt(str()->random(32)),
            ]);
        }

        try {
            $results = $creditNoteService->processAutomaticBalanceAdjustments($this->company, $user);

            if (! empty($results)) {
                \Log::info('Automatic credit note adjustments processed', [
                    'company_id' => $this->company->id,
                    'company_name' => $this->company->name,
                    'adjustments_count' => count($results),
                    'total_amount_applied' => array_sum(array_column($results, 'amount_applied')),
                ]);

                // Log activity for the batch process
                activity()
                    ->performedOn($this->company)
                    ->causedBy($user)
                    ->withProperties([
                        'action' => 'automatic_credit_adjustments',
                        'adjustments_count' => count($results),
                        'total_amount_applied' => array_sum(array_column($results, 'amount_applied')),
                        'results' => $results,
                    ])
                    ->log('Automatic credit note adjustments processed');
            }
        } catch (\Throwable $e) {
            \Log::error('Failed to process automatic credit note adjustments', [
                'company_id' => $this->company->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            throw $e;
        }
    }

    /**
     * Get the tags that should be assigned to the job.
     */
    public function tags(): array
    {
        return ['credit-notes', 'automatic-adjustments', 'company:'.$this->company->id];
    }
}
