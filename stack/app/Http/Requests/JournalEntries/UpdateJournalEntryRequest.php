<?php

namespace App\Http\Requests\JournalEntries;

use App\Http\Requests\BaseFormRequest;
use App\Models\Acct\JournalEntry;
use Illuminate\Validation\Rule;

class UpdateJournalEntryRequest extends BaseFormRequest
{
    public function authorize(): bool
    {
        return $this->hasCompanyPermission('ledger.entries.update') && 
               $this->validateRlsContext() &&
               $this->validateJournalEntryAccess() &&
               $this->validateJournalModifiable();
    }

    public function rules(): array
    {
        return [
            // Basic journal information
            'date' => 'required|date',
            'reference' => 'nullable|string|max:50',
            'description' => 'required|string|max:500',
            'currency' => [
                'required',
                'string',
                'size:3',
                Rule::exists('currencies', 'code')
            ],
            
            // Batch association
            'batch_id' => [
                'nullable',
                'uuid',
                Rule::exists('journal_batches', 'id')->where(function ($query) {
                    $query->where('company_id', $this->getCurrentCompanyId())
                          ->where('status', 'open');
                })
            ],
            
            // Journal lines
            'journal_lines' => 'required|array|min:2',
            'journal_lines.*.id' => 'nullable|uuid|exists:journal_lines,id',
            'journal_lines.*.account_id' => [
                'required',
                'uuid',
                Rule::exists('accounts', 'id')->where(function ($query) {
                    $query->where('company_id', $this->getCurrentCompanyId())
                          ->where('active', true);
                })
            ],
            'journal_lines.*.description' => 'nullable|string|max:255',
            'journal_lines.*.debit' => 'required|numeric|min:0|max:999999999.99',
            'journal_lines.*.credit' => 'required|numeric|min:0|max:999999999.99',
            'journal_lines.*._destroy' => 'boolean', // For marking lines to delete
            
            // Status changes (restricted)
            'status' => [
                'nullable',
                'string',
                Rule::in(['draft', 'posted'])
            ],
            
            // Supporting documents
            'attachments' => 'nullable|array|max:5',
            'attachments.*' => 'nullable|file|mimes:pdf,jpg,jpeg,png|max:5120',
            
            // Notes and memos
            'memo' => 'nullable|string|max:1000',
            'notes' => 'nullable|string|max:2000',
        ];
    }

    public function messages(): array
    {
        return [
            // Basic information
            'date.required' => 'Journal entry date is required',
            'date.date' => 'Journal entry date must be a valid date',
            'description.required' => 'Description is required',
            'description.max' => 'Description cannot exceed 500 characters',
            'reference.max' => 'Reference cannot exceed 50 characters',
            'currency.required' => 'Currency is required',
            'currency.exists' => 'Invalid currency selected',
            
            // Batch
            'batch_id.exists' => 'Selected journal batch is invalid or closed',
            
            // Journal lines
            'journal_lines.required' => 'At least 2 journal lines are required',
            'journal_lines.min' => 'At least 2 journal lines are required',
            'journal_lines.*.id.exists' => 'Journal line ID does not exist',
            'journal_lines.*.account_id.required' => 'Account is required for all journal lines',
            'journal_lines.*.account_id.exists' => 'Selected account is invalid or inactive',
            'journal_lines.*.debit.min' => 'Debit amount cannot be negative',
            'journal_lines.*.credit.min' => 'Credit amount cannot be negative',
            'journal_lines.*.description.max' => 'Line description cannot exceed 255 characters',
            
            // Status
            'status.in' => 'Status must be one of: draft or posted',
            
            // Attachments
            'attachments.max' => 'Cannot upload more than 5 attachments',
            'attachments.*.mimes' => 'Attachments must be PDF, JPG, JPEG, or PNG files',
            'attachments.*.max' => 'Attachment file size cannot exceed 5MB',
            
            // Notes
            'memo.max' => 'Memo cannot exceed 1000 characters',
            'notes.max' => 'Notes cannot exceed 2000 characters',
        ];
    }

    public function withValidator($validator)
    {
        $validator->after(function ($validator) {
            $journal = $this->getJournalEntry();
            
            if (!$journal) {
                return;
            }

            // Validate status change permissions
            $this->validateStatusChange($validator, $journal);
            
            // Validate journal lines
            $this->validateJournalLines($validator, $journal);
            
            // Validate business rules
            $this->validateBusinessRules($validator, $journal);
            
            // Validate date constraints
            $this->validateDateConstraints($validator, $journal);
        });
    }

    protected function prepareForValidation(): void
    {
        // Set default values for line items
        $journalLines = collect($this->input('journal_lines', []))->map(function ($item) {
            return array_merge([
                '_destroy' => false,
            ], $item);
        })->toArray();

        $this->merge(['journal_lines' => $journalLines]);
    }

    private function validateJournalEntryAccess(): bool
    {
        $journalId = $this->route('journal_entry');
        
        return JournalEntry::where('company_id', $this->getCurrentCompanyId())
            ->where('id', $journalId)
            ->exists();
    }

    private function validateJournalModifiable(): bool
    {
        $journal = $this->getJournalEntry();
        
        if (!$journal) {
            return false;
        }

        // Only draft entries can be modified (except for some posting)
        if ($journal->status === 'draft') {
            return true;
        }

        // Allow status changes to posted (posting operation)
        return $this->input('status') === 'posted';
    }

    private function validateStatusChange($validator, JournalEntry $journal): void
    {
        $newStatus = $this->input('status');
        
        if ($newStatus && $newStatus !== $journal->status) {
            if ($newStatus === 'posted') {
                // Check if all required fields are present for posting
                if (!$this->validatePostingRequirements()) {
                    $validator->errors()->add('status', 
                        'Cannot post journal entry: missing required fields or validation errors');
                }
                
                // Require posting permission
                if (!$this->hasCompanyPermission('ledger.entries.post')) {
                    $validator->errors()->add('status', 
                        'You do not have permission to post journal entries');
                }
            }
            
            // Can't change from posted back to draft
            if ($journal->status === 'posted' && $newStatus === 'draft') {
                $validator->errors()->add('status', 
                    'Cannot change posted journal entry back to draft');
            }
        }
    }

    private function validatePostingRequirements(): bool
    {
        // Check journal has required fields
        if (empty($this->input('date')) || empty($this->input('description'))) {
            return false;
        }

        // Check lines balance
        $journalLines = $this->input('journal_lines', []);
        $activeLines = collect($journalLines)->filter(fn($line) => !($line['_destroy'] ?? false));
        
        if ($activeLines->count() < 2) {
            return false;
        }

        $totalDebits = $activeLines->sum('debit');
        $totalCredits = $activeLines->sum('credit');
        
        return abs($totalDebits - $totalCredits) <= 0.01;
    }

    private function validateJournalLines($validator, JournalEntry $journal): void
    {
        $journalLines = $this->input('journal_lines', []);
        
        // Filter out lines marked for deletion
        $activeLines = collect($journalLines)->filter(fn($line) => !($line['_destroy'] ?? false));
        
        if ($activeLines->count() < 2) {
            $validator->errors()->add('journal_lines', 
                'At least 2 active journal lines are required');
            return;
        }

        $totalDebits = 0;
        $totalCredits = 0;

        foreach ($activeLines as $index => $line) {
            // Validate each line has either debit or credit, not both
            if (($line['debit'] > 0 && $line['credit'] > 0) ||
                ($line['debit'] == 0 && $line['credit'] == 0)) {
                $validator->errors()->add("journal_lines.{$index}", 
                    'Each line must have either a debit or credit amount, not both and not zero.');
            }

            $totalDebits += $line['debit'] ?? 0;
            $totalCredits += $line['credit'] ?? 0;
        }

        // Validate that the entry balances
        if (abs($totalDebits - $totalCredits) > 0.01) {
            $validator->errors()->add('journal_lines', 
                "Journal entry must balance. Debits: {$totalDebits}, Credits: {$totalCredits}");
        }
    }

    private function validateBusinessRules($validator, JournalEntry $journal): void
    {
        // Check if entry would affect accounting periods that are closed
        $entryDate = $this->input('date');
        
        if ($entryDate) {
            $closedPeriods = $this->getClosedAccountingPeriods();
            
            foreach ($closedPeriods as $period) {
                if ($entryDate >= $period['start_date'] && $entryDate <= $period['end_date']) {
                    $validator->errors()->add('date', 
                        'Cannot modify journal entry with date in closed accounting period: ' . $period['name']);
                    break;
                }
            }
        }

        // Log significant changes for audit
        if ($this->hasSignificantChanges($journal)) {
            \Log::info('Significant journal entry modification', [
                'journal_id' => $journal->id,
                'reference' => $journal->reference,
                'user_id' => $this->user()->id,
                'changes' => $this->getSignificantChanges($journal),
                'audit_context' => $this->getAuditContext(),
            ]);
        }
    }

    private function validateDateConstraints($validator, JournalEntry $journal): void
    {
        $newDate = $this->input('date');
        
        if ($newDate) {
            // Date cannot be too far in the future
            $futureLimit = now()->addDays(30);
            if ($newDate > $futureLimit) {
                $validator->errors()->add('date', 
                    'Journal entry date cannot be more than 30 days in the future');
            }
            
            // Date cannot be before the company's establishment date
            $company = $this->user()->currentCompany();
            if ($company && $newDate < $company->created_at) {
                $validator->errors()->add('date', 
                    'Journal entry date cannot be before company establishment date');
            }
        }
    }

    private function getClosedAccountingPeriods(): array
    {
        // This would typically come from a database query
        // For now, return empty array - implement as needed
        return [];
    }

    private function hasSignificantChanges(JournalEntry $journal): bool
    {
        $originalAmount = $journal->getTotalAmount();
        $journalLines = $this->input('journal_lines', []);
        $activeLines = collect($journalLines)->filter(fn($line) => !($line['_destroy'] ?? false));
        $newAmount = $activeLines->sum('debit');
        
        // Check for significant changes in amount
        if ($originalAmount > 0 && abs(($newAmount - $originalAmount) / $originalAmount) * 100 > 20) {
            return true;
        }

        // Check for changes in key fields
        if ($this->input('date') !== $journal->date->format('Y-m-d') ||
            $this->input('description') !== $journal->description) {
            return true;
        }

        return false;
    }

    private function getSignificantChanges(JournalEntry $journal): array
    {
        $changes = [];
        
        if ($this->input('date') !== $journal->date->format('Y-m-d')) {
            $changes[] = 'date changed';
        }
        
        if ($this->input('description') !== $journal->description) {
            $changes[] = 'description changed';
        }
        
        $originalAmount = $journal->getTotalAmount();
        $journalLines = $this->input('journal_lines', []);
        $activeLines = collect($journalLines)->filter(fn($line) => !($line['_destroy'] ?? false));
        $newAmount = $activeLines->sum('debit');
        
        if (abs($newAmount - $originalAmount) > 0.01) {
            $changes[] = 'amount changed';
        }
        
        return $changes;
    }

    /**
     * Get the journal entry being updated
     */
    public function getJournalEntry(): ?JournalEntry
    {
        $journalId = $this->route('journal_entry');
        
        return JournalEntry::where('company_id', $this->getCurrentCompanyId())
            ->where('id', $journalId)
            ->first();
    }

    /**
     * Get journal lines data separated by operation
     */
    public function getJournalLinesData(): array
    {
        $journalLines = $this->input('journal_lines', []);
        
        return [
            'to_create' => collect($journalLines)->filter(fn($line) => !isset($line['id']) && !($line['_destroy'] ?? false))->toArray(),
            'to_update' => collect($journalLines)->filter(fn($line) => isset($line['id']) && !($line['_destroy'] ?? false))->toArray(),
            'to_delete' => collect($journalLines)->filter(fn($line) => isset($line['id']) && ($line['_destroy'] ?? false))->pluck('id')->toArray(),
        ];
    }

    /**
     * Check if this is a posting operation
     */
    public function isPosting(): bool
    {
        return $this->input('status') === 'posted';
    }
}