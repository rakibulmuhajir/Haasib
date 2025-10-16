<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Journal Entry Actions Registry
    |--------------------------------------------------------------------------
    |
    | This file defines the command bus actions for journal entry operations.
    | Each action is registered with the command bus and can be dispatched
    | via the command name or directly via the service container.
    |
    */

    'journal.create' => [
        'class' => \Modules\Accounting\Domain\Ledgers\Actions\CreateManualJournalEntryAction::class,
        'description' => 'Create a manual journal entry with lines',
        'parameters' => [
            'company_id' => 'required|uuid',
            'description' => 'required|string|max:500',
            'date' => 'required|date',
            'type' => 'required|string|in:sales,purchase,payment,receipt,adjustment,closing,opening,reversal,automation',
            'reference' => 'nullable|string|max:100',
            'currency' => 'nullable|string|size:3',
            'exchange_rate' => 'nullable|numeric|min:0',
            'lines' => 'required|array|min:1',
            'lines.*.account_id' => 'required|uuid',
            'lines.*.debit_credit' => 'required|string|in:debit,credit',
            'lines.*.amount' => 'required|numeric|min:0',
            'lines.*.description' => 'nullable|string|max:500',
            'attachments' => 'nullable|array',
            'metadata' => 'nullable|array',
        ],
        'validation' => [
            'lines' => 'array|min:1',
            'lines.*.account_id' => 'required|uuid',
            'lines.*.debit_credit' => 'required|string|in:debit,credit',
            'lines.*.amount' => 'required|numeric|min:0',
        ],
    ],

    'journal.submit' => [
        'class' => \Modules\Accounting\Domain\Ledgers\Actions\SubmitJournalEntryAction::class,
        'description' => 'Submit a draft journal entry for approval',
        'parameters' => [
            'journal_entry_id' => 'required|uuid',
            'submit_note' => 'nullable|string|max:1000',
        ],
    ],

    'journal.approve' => [
        'class' => \Modules\Accounting\Domain\Ledgers\Actions\ApproveJournalEntryAction::class,
        'description' => 'Approve a journal entry pending approval',
        'parameters' => [
            'journal_entry_id' => 'required|uuid',
            'approval_note' => 'nullable|string|max:1000',
        ],
    ],

    'journal.post' => [
        'class' => \Modules\Accounting\Domain\Ledgers\Actions\PostJournalEntryAction::class,
        'description' => 'Post an approved journal entry to the ledger',
        'parameters' => [
            'journal_entry_id' => 'required|uuid',
            'post_note' => 'nullable|string|max:1000',
        ],
    ],

    'journal.reverse' => [
        'class' => \Modules\Accounting\Domain\Ledgers\Actions\ReverseJournalEntryAction::class,
        'description' => 'Create a reversing journal entry',
        'parameters' => [
            'journal_entry_id' => 'required|uuid',
            'reversal_date' => 'nullable|date|after_or_equal:today',
            'description_override' => 'nullable|string|max:500',
            'auto_post' => 'boolean',
        ],
    ],

    'journal.void' => [
        'class' => \Modules\Accounting\Domain\Ledgers\Actions\VoidJournalEntryAction::class,
        'description' => 'Void a journal entry',
        'parameters' => [
            'journal_entry_id' => 'required|uuid',
            'void_reason' => 'required|string|max:1000',
        ],
    ],

    'journal.auto' => [
        'class' => \Modules\Accounting\Domain\Ledgers\Actions\AutoJournalEntryAction::class,
        'description' => 'Create an automatic journal entry from source document',
        'parameters' => [
            'source_document_type' => 'required|string|max:100',
            'source_document_id' => 'required|uuid',
            'description' => 'required|string|max:500',
            'date' => 'required|date',
            'type' => 'required|string',
            'lines' => 'required|array|min:1',
            'company_id' => 'required|uuid',
            'auto_post' => 'boolean',
        ],
    ],
];
