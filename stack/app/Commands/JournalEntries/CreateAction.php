<?php

namespace App\Commands\JournalEntries;

use App\Commands\BaseCommand;
use App\Services\ServiceContext;
use App\Models\JournalEntry;
use App\Models\JournalLine;
use App\Models\Account;
use App\Models\JournalBatch;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Exception;

class CreateAction extends BaseCommand
{
    public function handle(): JournalEntry
    {
        return $this->executeInTransaction(function () {
            $companyId = $this->context->getCompanyId();
            $userId = $this->context->getUserId();
            
            if (!$companyId || !$userId) {
                throw new Exception('Invalid service context: missing company or user');
            }

            // Validate batch exists and belongs to company
            $batchId = $this->getValue('batch_id');
            if ($batchId) {
                $batch = JournalBatch::where('id', $batchId)
                    ->where('company_id', $companyId)
                    ->where('status', 'open')
                    ->firstOrFail();
            }

            // Calculate totals
            $journalLines = $this->getValue('journal_lines', []);
            $totalDebits = collect($journalLines)->sum('debit');
            $totalCredits = collect($journalLines)->sum('credit');

            // Validate entry balances
            if (abs($totalDebits - $totalCredits) > 0.01) {
                throw new Exception('Journal entry must balance. Debits: ' . $totalDebits . ', Credits: ' . $totalCredits);
            }

            // Create journal entry
            $journalEntry = JournalEntry::create([
                'id' => Str::uuid(),
                'company_id' => $companyId,
                'date' => $this->getValue('date'),
                'reference' => $this->getValue('reference'),
                'description' => $this->getValue('description'),
                'batch_id' => $batchId ?? null,
                'currency' => $this->getValue('currency'),
                'total_debits' => $totalDebits,
                'total_credits' => $totalCredits,
                'status' => 'draft', // Start as draft
                'created_by' => $userId,
            ]);

            // Create journal lines
            foreach ($journalLines as $index => $lineData) {
                // Validate each line has either debit or credit, not both
                if (($lineData['debit'] > 0 && $lineData['credit'] > 0) ||
                    ($lineData['debit'] == 0 && $lineData['credit'] == 0)) {
                    throw new Exception("Journal line {$index} must have either a debit or credit amount, not both and not zero.");
                }

                // Validate account exists and belongs to company
                $account = Account::where('id', $lineData['account_id'])
                    ->where('company_id', $companyId)
                    ->where('active', true)
                    ->firstOrFail();

                JournalLine::create([
                    'id' => Str::uuid(),
                    'journal_entry_id' => $journalEntry->id,
                    'account_id' => $account->id,
                    'description' => $lineData['description'] ?? $this->getValue('description'),
                    'debit' => $lineData['debit'],
                    'credit' => $lineData['credit'],
                    'created_by' => $userId,
                ]);
            }

            $this->audit('journal_entry.created', [
                'journal_entry_id' => $journalEntry->id,
                'reference' => $journalEntry->reference,
                'total_debits' => $totalDebits,
                'total_credits' => $totalCredits,
                'batch_id' => $batchId ?? null,
            ]);

            return $journalEntry->load(['journalLines.account', 'batch', 'createdBy']);
        });
    }
}