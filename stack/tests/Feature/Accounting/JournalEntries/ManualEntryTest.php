<?php

use App\Models\Account;
use App\Models\Company;
use App\Models\User;

uses(RefreshDatabase);

test('can create a manual journal entry with balanced lines', function () {
    $user = User::factory()->create();
    $company = Company::factory()->create();
    $account1 = Account::factory()->create(['company_id' => $company->id]);
    $account2 = Account::factory()->create(['company_id' => $company->id]);

    actingAs($user)->withHeaders([
        'X-Company-Id' => $company->id,
    ])->postJson('/api/ledger/journal-entries', [
        'description' => 'Test journal entry',
        'date' => '2025-01-15',
        'type' => 'adjustment',
        'lines' => [
            [
                'account_id' => $account1->id,
                'debit_credit' => 'debit',
                'amount' => 1000.00,
                'description' => 'Debit line',
            ],
            [
                'account_id' => $account2->id,
                'debit_credit' => 'credit',
                'amount' => 1000.00,
                'description' => 'Credit line',
            ],
        ],
    ])->assertCreated()
        ->assertJsonStructure([
            'data' => [
                'id',
                'description',
                'date',
                'type',
                'status',
                'currency',
                'lines' => [
                    '*' => [
                        'account_id',
                        'debit_credit',
                        'amount',
                        'description',
                    ],
                ],
                'totals' => [
                    'total_debits',
                    'total_credits',
                    'balanced',
                ],
            ],
        ]);
});

test('cannot create unbalanced journal entry', function () {
    $user = User::factory()->create();
    $company = Company::factory()->create();
    $account1 = Account::factory()->create(['company_id' => $company->id]);
    $account2 = Account::factory()->create(['company_id' => $company->id]);

    actingAs($user)->withHeaders([
        'X-Company-Id' => $company->id,
    ])->postJson('/api/ledger/journal-entries', [
        'description' => 'Unbalanced entry',
        'date' => '2025-01-15',
        'type' => 'adjustment',
        'lines' => [
            [
                'account_id' => $account1->id,
                'debit_credit' => 'debit',
                'amount' => 1000.00,
                'description' => 'Debit line',
            ],
            [
                'account_id' => $account2->id,
                'debit_credit' => 'credit',
                'amount' => 800.00, // Unbalanced
                'description' => 'Credit line',
            ],
        ],
    ])->assertUnprocessable()
        ->assertJsonValidationErrors(['lines']);
});

test('can submit draft journal entry for approval', function () {
    $user = User::factory()->create();
    $company = Company::factory()->create();
    $account1 = Account::factory()->create(['company_id' => $company->id]);
    $account2 = Account::factory()->create(['company_id' => $company->id]);

    // Create journal entry first
    $response = actingAs($user)->withHeaders([
        'X-Company-Id' => $company->id,
    ])->postJson('/api/ledger/journal-entries', [
        'description' => 'Test journal entry',
        'date' => '2025-01-15',
        'type' => 'adjustment',
        'lines' => [
            [
                'account_id' => $account1->id,
                'debit_credit' => 'debit',
                'amount' => 1000.00,
            ],
            [
                'account_id' => $account2->id,
                'debit_credit' => 'credit',
                'amount' => 1000.00,
            ],
        ],
    ]);

    $entryId = $response->json('data.id');

    // Submit for approval
    actingAs($user)->withHeaders([
        'X-Company-Id' => $company->id,
    ])->postJson("/api/ledger/journal-entries/{$entryId}/submit", [
        'submit_note' => 'Ready for review',
    ])->assertOk()
        ->assertJsonPath('data.status', 'pending_approval');
});

test('can approve pending journal entry', function () {
    $user = User::factory()->create();
    $company = Company::factory()->create();
    $account1 = Account::factory()->create(['company_id' => $company->id]);
    $account2 = Account::factory()->create(['company_id' => $company->id]);

    // Create and submit journal entry
    $response = actingAs($user)->withHeaders([
        'X-Company-Id' => $company->id,
    ])->postJson('/api/ledger/journal-entries', [
        'description' => 'Test journal entry',
        'date' => '2025-01-15',
        'type' => 'adjustment',
        'lines' => [
            [
                'account_id' => $account1->id,
                'debit_credit' => 'debit',
                'amount' => 1000.00,
            ],
            [
                'account_id' => $account2->id,
                'debit_credit' => 'credit',
                'amount' => 1000.00,
            ],
        ],
    ]);

    $entryId = $response->json('data.id');

    actingAs($user)->withHeaders([
        'X-Company-Id' => $company->id,
    ])->postJson("/api/ledger/journal-entries/{$entryId}/submit");

    // Approve the entry
    actingAs($user)->withHeaders([
        'X-Company-Id' => $company->id,
    ])->postJson("/api/ledger/journal-entries/{$entryId}/approve", [
        'approval_note' => 'Looks good',
    ])->assertOk()
        ->assertJsonPath('data.status', 'approved')
        ->assertJsonPath('data.approved_by', $user->id);
});

test('can post approved journal entry', function () {
    $user = User::factory()->create();
    $company = Company::factory()->create();
    $account1 = Account::factory()->create(['company_id' => $company->id]);
    $account2 = Account::factory()->create(['company_id' => $company->id]);

    // Create, submit, and approve journal entry
    $response = actingAs($user)->withHeaders([
        'X-Company-Id' => $company->id,
    ])->postJson('/api/ledger/journal-entries', [
        'description' => 'Test journal entry',
        'date' => '2025-01-15',
        'type' => 'adjustment',
        'lines' => [
            [
                'account_id' => $account1->id,
                'debit_credit' => 'debit',
                'amount' => 1000.00,
            ],
            [
                'account_id' => $account2->id,
                'debit_credit' => 'credit',
                'amount' => 1000.00,
            ],
        ],
    ]);

    $entryId = $response->json('data.id');

    actingAs($user)->withHeaders([
        'X-Company-Id' => $company->id,
    ])->postJson("/api/ledger/journal-entries/{$entryId}/submit");

    actingAs($user)->withHeaders([
        'X-Company-Id' => $company->id,
    ])->postJson("/api/ledger/journal-entries/{$entryId}/approve");

    // Post the entry
    actingAs($user)->withHeaders([
        'X-Company-Id' => $company->id,
    ])->postJson("/api/ledger/journal-entries/{$entryId}/post")
        ->assertOk()
        ->assertJsonPath('data.status', 'posted')
        ->assertJsonPath('data.posted_by', $user->id)
        ->assertJsonPath('data.posted_at', fn ($date) => ! is_null($date));
});

test('cannot post unapproved journal entry', function () {
    $user = User::factory()->create();
    $company = Company::factory()->create();
    $account1 = Account::factory()->create(['company_id' => $company->id]);
    $account2 = Account::factory()->create(['company_id' => $company->id]);

    // Create journal entry but don't approve it
    $response = actingAs($user)->withHeaders([
        'X-Company-Id' => $company->id,
    ])->postJson('/api/ledger/journal-entries', [
        'description' => 'Test journal entry',
        'date' => '2025-01-15',
        'type' => 'adjustment',
        'lines' => [
            [
                'account_id' => $account1->id,
                'debit_credit' => 'debit',
                'amount' => 1000.00,
            ],
            [
                'account_id' => $account2->id,
                'debit_credit' => 'credit',
                'amount' => 1000.00,
            ],
        ],
    ]);

    $entryId = $response->json('data.id');

    // Try to post unapproved entry
    actingAs($user)->withHeaders([
        'X-Company-Id' => $company->id,
    ])->postJson("/api/ledger/journal-entries/{$entryId}/post")
        ->assertForbidden()
        ->assertJson(['message' => 'Journal entry must be approved before posting']);
});

test('can create reversal for posted journal entry', function () {
    $user = User::factory()->create();
    $company = Company::factory()->create();
    $account1 = Account::factory()->create(['company_id' => $company->id]);
    $account2 = Account::factory()->create(['company_id' => $company->id]);

    // Create and post journal entry
    $response = actingAs($user)->withHeaders([
        'X-Company-Id' => $company->id,
    ])->postJson('/api/ledger/journal-entries', [
        'description' => 'Original journal entry',
        'date' => '2025-01-15',
        'type' => 'adjustment',
        'lines' => [
            [
                'account_id' => $account1->id,
                'debit_credit' => 'debit',
                'amount' => 1000.00,
            ],
            [
                'account_id' => $account2->id,
                'debit_credit' => 'credit',
                'amount' => 1000.00,
            ],
        ],
    ]);

    $entryId = $response->json('data.id');

    actingAs($user)->withHeaders([
        'X-Company-Id' => $company->id,
    ])->postJson("/api/ledger/journal-entries/{$entryId}/submit");

    actingAs($user)->withHeaders([
        'X-Company-Id' => $company->id,
    ])->postJson("/api/ledger/journal-entries/{$entryId}/approve");

    actingAs($user)->withHeaders([
        'X-Company-Id' => $company->id,
    ])->postJson("/api/ledger/journal-entries/{$entryId}/post");

    // Create reversal
    actingAs($user)->withHeaders([
        'X-Company-Id' => $company->id,
    ])->postJson("/api/ledger/journal-entries/{$entryId}/reverse", [
        'reversal_date' => '2025-01-20',
        'description_override' => 'Reversal of original entry',
        'auto_post' => true,
    ])->assertCreated()
        ->assertJsonStructure([
            'data' => [
                'id',
                'description',
                'date',
                'type',
                'status',
                'reverse_of_entry_id',
                'lines' => [
                    '*' => [
                        'account_id',
                        'debit_credit',
                        'amount',
                    ],
                ],
            ],
        ])
        ->assertJsonPath('data.type', 'reversal')
        ->assertJsonPath('data.reverse_of_entry_id', $entryId)
        ->assertJsonPath('data.status', 'posted');
});

test('reversal entry has inverted amounts and accounts', function () {
    $user = User::factory()->create();
    $company = Company::factory()->create();
    $account1 = Account::factory()->create(['company_id' => $company->id]);
    $account2 = Account::factory()->create(['company_id' => $company->id]);

    // Create and post journal entry
    $response = actingAs($user)->withHeaders([
        'X-Company-Id' => $company->id,
    ])->postJson('/api/ledger/journal-entries', [
        'description' => 'Original journal entry',
        'date' => '2025-01-15',
        'type' => 'adjustment',
        'lines' => [
            [
                'account_id' => $account1->id,
                'debit_credit' => 'debit',
                'amount' => 1000.00,
                'description' => 'Original debit',
            ],
            [
                'account_id' => $account2->id,
                'debit_credit' => 'credit',
                'amount' => 1000.00,
                'description' => 'Original credit',
            ],
        ],
    ]);

    $entryId = $response->json('data.id');

    // Post the entry
    actingAs($user)->withHeaders([
        'X-Company-Id' => $company->id,
    ])->postJson("/api/ledger/journal-entries/{$entryId}/submit");

    actingAs($user)->withHeaders([
        'X-Company-Id' => $company->id,
    ])->postJson("/api/ledger/journal-entries/{$entryId}/approve");

    actingAs($user)->withHeaders([
        'X-Company-Id' => $company->id,
    ])->postJson("/api/ledger/journal-entries/{$entryId}/post");

    // Create reversal
    $reversalResponse = actingAs($user)->withHeaders([
        'X-Company-Id' => $company->id,
    ])->postJson("/api/ledger/journal-entries/{$entryId}/reverse", [
        'reversal_date' => '2025-01-20',
        'auto_post' => true,
    ]);

    $reversalLines = collect($reversalResponse->json('data.lines'));

    // Check that debit/credit are inverted
    $originalDebitAccount = $account1->id;
    $originalCreditAccount = $account2->id;

    $reversalDebitLine = $reversalLines->firstWhere('account_id', $originalCreditAccount);
    $reversalCreditLine = $reversalLines->firstWhere('account_id', $originalDebitAccount);

    expect($reversalDebitLine['debit_credit'])->toBe('debit');
    expect($reversalCreditLine['debit_credit'])->toBe('credit');
    expect($reversalDebitLine['amount'])->toBe(1000.00);
    expect($reversalCreditLine['amount'])->toBe(1000.00);
});

test('can view journal entry details with audit trail', function () {
    $user = User::factory()->create();
    $company = Company::factory()->create();
    $account1 = Account::factory()->create(['company_id' => $company->id]);
    $account2 = Account::factory()->create(['company_id' => $company->id]);

    // Create journal entry
    $response = actingAs($user)->withHeaders([
        'X-Company-Id' => $company->id,
    ])->postJson('/api/ledger/journal-entries', [
        'description' => 'Test journal entry',
        'date' => '2025-01-15',
        'type' => 'adjustment',
        'lines' => [
            [
                'account_id' => $account1->id,
                'debit_credit' => 'debit',
                'amount' => 1000.00,
            ],
            [
                'account_id' => $account2->id,
                'debit_credit' => 'credit',
                'amount' => 1000.00,
            ],
        ],
    ]);

    $entryId = $response->json('data.id');

    // View entry details
    actingAs($user)->withHeaders([
        'X-Company-Id' => $company->id,
    ])->getJson("/api/ledger/journal-entries/{$entryId}")
        ->assertOk()
        ->assertJsonStructure([
            'data' => [
                'id',
                'description',
                'date',
                'type',
                'status',
                'lines' => [
                    '*' => [
                        'id',
                        'account_id',
                        'account_code',
                        'account_name',
                        'debit_credit',
                        'amount',
                        'description',
                    ],
                ],
                'totals' => [
                    'total_debits',
                    'total_credits',
                    'balanced',
                ],
                'sources' => [
                    '*' => [
                        'source_type',
                        'source_reference',
                        'link_type',
                    ],
                ],
            ],
        ]);

    // View audit trail
    actingAs($user)->withHeaders([
        'X-Company-Id' => $company->id,
    ])->getJson("/api/ledger/journal-entries/{$entryId}/audit")
        ->assertOk()
        ->assertJsonStructure([
            'data' => [
                '*' => [
                    'id',
                    'event_type',
                    'actor_id',
                    'created_at',
                    'description',
                ],
            ],
        ]);
});

test('can list journal entries with filters', function () {
    $user = User::factory()->create();
    $company = Company::factory()->create();
    $account1 = Account::factory()->create(['company_id' => $company->id]);
    $account2 = Account::factory()->create(['company_id' => $company->id]);

    // Create multiple journal entries
    actingAs($user)->withHeaders([
        'X-Company-Id' => $company->id,
    ])->postJson('/api/ledger/journal-entries', [
        'description' => 'First entry',
        'date' => '2025-01-15',
        'type' => 'adjustment',
        'lines' => [
            [
                'account_id' => $account1->id,
                'debit_credit' => 'debit',
                'amount' => 1000.00,
            ],
            [
                'account_id' => $account2->id,
                'debit_credit' => 'credit',
                'amount' => 1000.00,
            ],
        ],
    ]);

    actingAs($user)->withHeaders([
        'X-Company-Id' => $company->id,
    ])->postJson('/api/ledger/journal-entries', [
        'description' => 'Second entry',
        'date' => '2025-01-16',
        'type' => 'adjustment',
        'lines' => [
            [
                'account_id' => $account1->id,
                'debit_credit' => 'debit',
                'amount' => 2000.00,
            ],
            [
                'account_id' => $account2->id,
                'debit_credit' => 'credit',
                'amount' => 2000.00,
            ],
        ],
    ]);

    // List entries with status filter
    actingAs($user)->withHeaders([
        'X-Company-Id' => $company->id,
    ])->getJson('/api/ledger/journal-entries?filter[status]=draft')
        ->assertOk()
        ->assertJsonCount(2, 'data')
        ->assertJsonPath('data.*.status', ['draft', 'draft']);

    // List entries with date range filter
    actingAs($user)->withHeaders([
        'X-Company-Id' => $company->id,
    ])->getJson('/api/ledger/journal-entries?filter[date_from]=2025-01-16&filter[date_to]=2025-01-16')
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.description', 'Second entry');

    // List entries with type filter
    actingAs($user)->withHeaders([
        'X-Company-Id' => $company->id,
    ])->getJson('/api/ledger/journal-entries?filter[type]=adjustment')
        ->assertOk()
        ->assertJsonCount(2, 'data');
});

test('validates required fields when creating journal entry', function () {
    $user = User::factory()->create();
    $company = Company::factory()->create();

    actingAs($user)->withHeaders([
        'X-Company-Id' => $company->id,
    ])->postJson('/api/ledger/journal-entries', [])
        ->assertUnprocessable()
        ->assertJsonValidationErrors([
            'description',
            'date',
            'type',
            'lines',
        ]);
});

test('validates line structure when creating journal entry', function () {
    $user = User::factory()->create();
    $company = Company::factory()->create();

    actingAs($user)->withHeaders([
        'X-Company-Id' => $company->id,
    ])->postJson('/api/ledger/journal-entries', [
        'description' => 'Test entry',
        'date' => '2025-01-15',
        'type' => 'adjustment',
        'lines' => [
            [
                'debit_credit' => 'invalid',
                'amount' => -100, // Invalid amount
                'description' => str_repeat('a', 600), // Too long
            ],
        ],
    ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors([
            'lines.0.account_id',
            'lines.0.debit_credit',
            'lines.0.amount',
            'lines.0.description',
        ]);
});
