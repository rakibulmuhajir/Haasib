<?php

namespace Modules\Accounting\Domain\Ledgers\Actions;

use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use App\Models\JournalEntry;

class CreateJournalEntryAction
{
    /**
     * Execute journal entry creation.
     */
    public function execute(array $data): array
    {
        // Validate input data
        $validated = Validator::make($data, [
            'payment_id' => 'nullable|uuid|exists:invoicing.payments,payment_id',
            'invoice_id' => 'nullable|uuid|exists:acct.invoices,invoice_id',
            'allocation_id' => 'nullable|uuid|exists:invoicing.payment_allocations,id',
            'company_id' => 'required|uuid|exists:public.companies,id',
            'entry_type' => 'required|string|in:payment,reversal,allocation,allocation_reversal',
            'account_code' => 'required|string|max:10|exists:accounting.chart_of_accounts,account_code',
            'debit_amount' => 'required|numeric|min:0',
            'credit_amount' => 'required|numeric|min:0',
            'description' => 'required|string|max:500',
            'reference' => 'nullable|string|max:100',
            'date' => 'required|date',
            'metadata' => 'nullable|array',
        ])->validate();

        // Ensure either debit or credit amount is provided, but not both
        if ($validated['debit_amount'] > 0 && $validated['credit_amount'] > 0) {
            throw new \InvalidArgumentException('Journal entry cannot have both debit and credit amounts');
        }

        if ($validated['debit_amount'] == 0 && $validated['credit_amount'] == 0) {
            throw new \InvalidArgumentException('Journal entry must have either a debit or credit amount');
        }

        return DB::transaction(function () use ($validated) {
            // Create journal entry
            $journalEntry = JournalEntry::create([
                'id' => Str::uuid(),
                'payment_id' => $validated['payment_id'],
                'invoice_id' => $validated['invoice_id'],
                'allocation_id' => $validated['allocation_id'],
                'company_id' => $validated['company_id'],
                'entry_type' => $validated['entry_type'],
                'account_code' => $validated['account_code'],
                'debit_amount' => $validated['debit_amount'],
                'credit_amount' => $validated['credit_amount'],
                'description' => $validated['description'],
                'reference' => $validated['reference'],
                'date' => $validated['date'],
                'balance' => $validated['debit_amount'] - $validated['credit_amount'],
                'status' => 'posted',
                'metadata' => json_encode($validated['metadata'] ?? []),
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            return [
                'id' => $journalEntry->id,
                'payment_id' => $journalEntry->payment_id,
                'invoice_id' => $journalEntry->invoice_id,
                'allocation_id' => $journalEntry->allocation_id,
                'company_id' => $journalEntry->company_id,
                'entry_type' => $journalEntry->entry_type,
                'account_code' => $journalEntry->account_code,
                'debit_amount' => $journalEntry->debit_amount,
                'credit_amount' => $journalEntry->credit_amount,
                'description' => $journalEntry->description,
                'reference' => $journalEntry->reference,
                'date' => $journalEntry->date,
                'balance' => $journalEntry->balance,
                'status' => $journalEntry->status,
                'metadata' => $validated['metadata'] ?? [],
                'created_at' => $journalEntry->created_at,
            ];
        });
    }
}