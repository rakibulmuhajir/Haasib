<?php

namespace App\Http\Requests\JournalEntries;

use App\Http\Requests\BaseFormRequest;
use App\Models\JournalEntry;

class PostJournalEntryRequest extends BaseFormRequest
{
    public function authorize(): bool
    {
        $journalEntry = $this->route('journal_entry');
        
        if (!$journalEntry || !$this->hasCompanyPermission('ledger.entries.post')) {
            return false;
        }

        // Only draft entries can be posted
        return $journalEntry->status === 'draft' && 
               $journalEntry->company_id === $this->getCurrentCompanyId();
    }

    public function rules(): array
    {
        return [
            // No additional data needed for posting - just authorization
        ];
    }

    public function messages(): array
    {
        return [
            // Authorization messages handled in authorize method
        ];
    }
}