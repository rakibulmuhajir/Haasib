<?php

namespace App\Http\Requests\JournalEntries;

use App\Http\Requests\BaseFormRequest;
use App\Models\JournalEntry;

class VoidJournalEntryRequest extends BaseFormRequest
{
    public function authorize(): bool
    {
        $journalEntry = $this->route('journal_entry');
        
        if (!$journalEntry || !$this->hasCompanyPermission('ledger.entries.void')) {
            return false;
        }

        // Only posted entries can be voided
        return $journalEntry->status === 'posted' && 
               $journalEntry->company_id === $this->getCurrentCompanyId();
    }

    public function rules(): array
    {
        return [
            'reason' => 'nullable|string|max:500',
        ];
    }

    public function messages(): array
    {
        return [
            'reason.max' => 'Void reason cannot exceed 500 characters',
        ];
    }
}