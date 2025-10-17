<?php

namespace Modules\Ledger\Jobs;

use App\Models\BankReconciliation;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Modules\Ledger\Actions\BankReconciliation\RunAutoMatch;

class RunAutoMatchJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public int $timeout = 300; // 5 minutes

    public function __construct(
        public BankReconciliation $reconciliation,
        public User $user,
        public array $options = []
    ) {
        $this->onQueue('bank-reconciliation');
    }

    public function handle(): void
    {
        try {
            $action = RunAutoMatch::forReconciliation($this->reconciliation, $this->user, $this->options);
            $matchesCount = $action->execute();

            Log::info('Auto-match completed for reconciliation', [
                'reconciliation_id' => $this->reconciliation->id,
                'matches_created' => $matchesCount,
                'user_id' => $this->user->id,
            ]);

            // Broadcast completion event
            event(new \Modules\Ledger\Events\AutoMatchCompleted(
                $this->reconciliation,
                $matchesCount
            ));

        } catch (\Exception $e) {
            Log::error('Auto-match job failed', [
                'reconciliation_id' => $this->reconciliation->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            throw $e;
        }
    }

    public function failed(\Exception $exception): void
    {
        Log::error('Auto-match job failed permanently', [
            'reconciliation_id' => $this->reconciliation->id,
            'attempt' => $this->attempts(),
            'error' => $exception->getMessage(),
        ]);

        // Notify user about the failure
        $this->user->notify(
            new \App\Notifications\AutoMatchFailedNotification(
                $this->reconciliation,
                $exception->getMessage()
            )
        );
    }

    public function getDisplayName(): string
    {
        return "Auto-Match: Reconciliation {$this->reconciliation->id}";
    }

    public function tags(): array
    {
        return [
            'bank-reconciliation',
            'auto-match',
            'company:'.$this->reconciliation->company_id,
            'account:'.$this->reconciliation->ledger_account_id,
        ];
    }
}
