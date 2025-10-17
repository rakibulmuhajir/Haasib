<?php

namespace Modules\Ledger\Jobs;

use App\Models\BankStatement;
use App\Models\BankStatementLine;
use Exception;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Modules\Ledger\Services\BankStatementImportService;

class NormalizeBankStatement implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public int $timeout = 300; // 5 minutes

    public function __construct(
        public BankStatement $bankStatement,
        public array $options = []
    ) {
        $this->onQueue('bank-reconciliation');
    }

    public function handle(): void
    {
        try {
            DB::transaction(function () {
                $this->bankStatement->update(['status' => 'processing']);

                $parser = new BankStatementImportService;
                $statementLines = $parser->parseStatement($this->bankStatement);

                $validation = $parser->validateStatement($this->bankStatement, $statementLines);

                if (! empty($validation['errors'])) {
                    $this->failWithErrors($validation['errors']);

                    return;
                }

                // Remove existing lines if this is a reprocessing
                BankStatementLine::where('statement_id', $this->bankStatement->id)->delete();

                // Create statement lines in batches for better performance
                $batchSize = 100;
                $linesArray = $statementLines->toArray();

                for ($i = 0; $i < count($linesArray); $i += $batchSize) {
                    $batch = array_slice($linesArray, $i, $batchSize);
                    BankStatementLine::insert($batch);
                }

                // Update statement status and metadata
                $this->bankStatement->update([
                    'status' => 'processed',
                    'processed_at' => now(),
                ]);

                // Log warnings if any
                if (! empty($validation['warnings'])) {
                    foreach ($validation['warnings'] as $warning) {
                        Log::warning('Bank statement normalization warning', [
                            'statement_id' => $this->bankStatement->id,
                            'warning' => $warning,
                        ]);
                    }
                }

                // Dispatch events for further processing
                $this->dispatchCompletionEvents($validation);

                Log::info('Bank statement normalized successfully', [
                    'statement_id' => $this->bankStatement->id,
                    'lines_count' => $statementLines->count(),
                    'warnings_count' => count($validation['warnings']),
                ]);
            });

        } catch (Exception $e) {
            Log::error('Bank statement normalization failed', [
                'statement_id' => $this->bankStatement->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            $this->bankStatement->update(['status' => 'failed']);
            throw $e;
        }
    }

    public function failed(Exception $exception): void
    {
        Log::error('Bank statement normalization job failed', [
            'statement_id' => $this->bankStatement->id,
            'attempt' => $this->attempts(),
            'error' => $exception->getMessage(),
        ]);

        $this->bankStatement->update(['status' => 'failed']);

        // Notify user about the failure
        $this->notifyFailure($exception);
    }

    private function failWithErrors(array $errors): void
    {
        $errorMessage = 'Statement validation failed: '.implode('; ', $errors);

        $this->bankStatement->update(['status' => 'failed']);

        Log::error('Bank statement validation failed', [
            'statement_id' => $this->bankStatement->id,
            'errors' => $errors,
        ]);

        throw new Exception($errorMessage);
    }

    private function dispatchCompletionEvents(array $validation): void
    {
        // Dispatch event for statement processing completion
        event(new \Modules\Ledger\Events\BankStatementProcessed(
            $this->bankStatement,
            $validation['summary']
        ));

        // If there are significant warnings, dispatch a warning event
        if (count($validation['warnings']) > 0) {
            event(new \Modules\Ledger\Events\BankStatementProcessedWithWarnings(
                $this->bankStatement,
                $validation['warnings'],
                $validation['summary']
            ));
        }
    }

    private function notifyFailure(Exception $exception): void
    {
        // Send notification to the user who imported the statement
        if ($this->bankStatement->importedBy) {
            $this->bankStatement->importedBy->notify(
                new \App\Notifications\BankStatementImportFailed(
                    $this->bankStatement,
                    $exception->getMessage()
                )
            );
        }
    }

    public function getDisplayName(): string
    {
        return "Normalize Bank Statement: {$this->bankStatement->statement_name}";
    }

    public function tags(): array
    {
        return [
            'bank-reconciliation',
            'statement-normalization',
            'company:'.$this->bankStatement->company_id,
            'account:'.$this->bankStatement->ledger_account_id,
            'format:'.$this->bankStatement->format,
        ];
    }
}
