<?php

use App\Models\Account;
use App\Models\JournalAudit;
use App\Models\JournalEntry;
use App\Models\JournalEntrySource;
use App\Models\JournalTransaction;
use Illuminate\Support\Facades\Event;
use Modules\Accounting\Domain\Ledgers\Events\JournalEntryCreated;

beforeEach(function () {
    $this->company = createCompany();
    $this->user = createUser(['company_id' => $this->company->id]);

    // Create test accounts
    $this->cashAccount = Account::factory()->create([
        'company_id' => $this->company->id,
        'code' => '10000',
        'name' => 'Cash',
        'normal_balance' => 'debit',
    ]);

    $this->receivablesAccount = Account::factory()->create([
        'company_id' => $this->company->id,
        'code' => '12000',
        'name' => 'Accounts Receivable',
        'normal_balance' => 'debit',
    ]);

    $this->revenueAccount = Account::factory()->create([
        'company_id' => $this->company->id,
        'code' => '40000',
        'name' => 'Sales Revenue',
        'normal_balance' => 'credit',
    ]);
});

uses(Illuminate\Foundation\Testing\RefreshDatabase::class);

// Helper functions for this test file
function createPostedJournalEntryForTest($company, $receivablesAccount, $revenueAccount, $overrides = []): JournalEntry
{
    $amount = $overrides['amount'] ?? 1000;
    $date = $overrides['date'] ?? now();

    $journalEntry = JournalEntry::factory()->create(array_merge([
        'company_id' => $company->id,
        'status' => 'posted',
        'date' => $date,
        'posted_at' => now(),
    ], $overrides));

    // Create transactions
    JournalTransaction::factory()->create([
        'journal_entry_id' => $journalEntry->id,
        'account_id' => $overrides['debit_account_id'] ?? $receivablesAccount->id,
        'debit_credit' => 'debit',
        'amount' => $amount,
        'currency' => 'USD',
    ]);

    JournalTransaction::factory()->create([
        'journal_entry_id' => $journalEntry->id,
        'account_id' => $overrides['credit_account_id'] ?? $revenueAccount->id,
        'debit_credit' => 'credit',
        'amount' => $amount,
        'currency' => 'USD',
    ]);

    return $journalEntry;
}

function createJournalEntryForTest($company, $overrides = []): JournalEntry
{
    return JournalEntry::factory()->create(array_merge([
        'company_id' => $company->id,
        'status' => 'draft',
    ], $overrides));
}

describe('Journal Audit, Search & Trial Balance', function () {

    it('creates audit records for journal entry lifecycle events', function () {
        // Arrange: Create a journal entry
        $journalEntry = JournalEntry::factory()->create([
            'company_id' => $this->company->id,
            'status' => 'draft',
        ]);

        // Act: Fire journal entry events
        Event::dispatch(new JournalEntryCreated($journalEntry));

        // Assert: Audit record was created
        expect(JournalAudit::count())->toBe(1);

        $auditRecord = JournalAudit::first();
        expect($auditRecord)->toBeTruthy();
        expect($auditRecord->journal_entry_id)->toBe($journalEntry->id);
        expect($auditRecord->event_type)->toBe('created');
        expect($auditRecord->payload)->toHaveKey('timestamp');
    });

    it('retrieves audit timeline for a journal entry', function () {
        // Arrange: Create journal entry with multiple events
        $journalEntry = JournalEntry::factory()->create([
            'company_id' => $this->company->id,
            'status' => 'draft',
        ]);

        // Create multiple audit records
        JournalAudit::factory()->count(3)->create([
            'journal_entry_id' => $journalEntry->id,
            'event_type' => 'created',
            'payload' => ['test' => 'data'],
        ]);

        // Act: Get audit timeline
        $response = $this->actingAs($this->user)
            ->getJson("/api/ledger/journal-entries/{$journalEntry->id}/audit");

        // Assert: Audit timeline returned
        $response->assertStatus(200);
        $response->assertJsonCount(3);
        $response->assertJsonStructure([
            '*' => [
                'id',
                'journal_entry_id',
                'event_type',
                'payload',
                'actor_id',
                'created_at',
            ],
        ]);
    });

    it('searches journal entries by description', function () {
        // Arrange: Create journal entries with different descriptions
        $journalEntry1 = JournalEntry::factory()->create([
            'company_id' => $this->company->id,
            'description' => 'Test payment from customer',
            'status' => 'posted',
        ]);

        $journalEntry2 = JournalEntry::factory()->create([
            'company_id' => $this->company->id,
            'description' => 'Another unrelated entry',
            'status' => 'posted',
        ]);

        // Act: Search for entries containing 'payment'
        $response = $this->actingAs($this->user)
            ->getJson('/api/ledger/journal-entries?search=payment');

        // Assert: Search results
        $response->assertStatus(200);
        $response->assertJsonCount(1);
        $response->assertJsonFragment(['description' => 'Test payment from customer']);
    });

    it('filters journal entries by status', function () {
        // Arrange: Create entries with different statuses
        $draftEntry = JournalEntry::factory()->create([
            'company_id' => $this->company->id,
            'status' => 'draft',
        ]);

        $postedEntry = JournalEntry::factory()->create([
            'company_id' => $this->company->id,
            'status' => 'posted',
        ]);

        $approvedEntry = JournalEntry::factory()->create([
            'company_id' => $this->company->id,
            'status' => 'approved',
        ]);

        // Act: Filter by posted status
        $response = $this->actingAs($this->user)
            ->getJson('/api/ledger/journal-entries?status=posted');

        // Assert: Only posted entries returned
        $response->assertStatus(200);
        $response->assertJsonCount(1);
        $response->assertJsonFragment(['status' => 'posted']);
    });

    it('filters journal entries by date range', function () {
        // Arrange: Create entries with different dates
        $oldEntry = JournalEntry::factory()->create([
            'company_id' => $this->company->id,
            'date' => now()->subDays(10),
            'status' => 'posted',
        ]);

        $recentEntry = JournalEntry::factory()->create([
            'company_id' => $this->company->id,
            'date' => now()->subDays(2),
            'status' => 'posted',
        ]);

        $futureEntry = JournalEntry::factory()->create([
            'company_id' => $this->company->id,
            'date' => now()->addDays(5),
            'status' => 'posted',
        ]);

        // Act: Filter by date range (last 3 days)
        $response = $this->actingAs($this->user)
            ->getJson('/api/ledger/journal-entries?date_from='.now()->subDays(3)->format('Y-m-d'));

        // Assert: Only recent and future entries returned
        $response->assertStatus(200);
        $response->assertJsonCount(2);
    });

    it('filters journal entries by type', function () {
        // Arrange: Create entries with different types
        $salesEntry = JournalEntry::factory()->create([
            'company_id' => $this->company->id,
            'type' => 'sales',
            'status' => 'posted',
        ]);

        $paymentEntry = JournalEntry::factory()->create([
            'company_id' => $this->company->id,
            'type' => 'payment',
            'status' => 'posted',
        ]);

        // Act: Filter by sales type
        $response = $this->actingAs($this->user)
            ->getJson('/api/ledger/journal-entries?type=sales');

        // Assert: Only sales entries returned
        $response->assertStatus(200);
        $response->assertJsonCount(1);
        $response->assertJsonFragment(['type' => 'sales']);
    });

    it('searches journal entries by reference number', function () {
        // Arrange: Create entry with reference
        $journalEntry = JournalEntry::factory()->create([
            'company_id' => $this->company->id,
            'reference' => 'INV-2024-001',
            'status' => 'posted',
        ]);

        // Act: Search by reference
        $response = $this->actingAs($this->user)
            ->getJson('/api/ledger/journal-entries?search=INV-2024-001');

        // Assert: Entry found
        $response->assertStatus(200);
        $response->assertJsonCount(1);
        $response->assertJsonFragment(['reference' => 'INV-2024-001']);
    });

    it('includes source document information in search results', function () {
        // Arrange: Create journal entry with source document
        $journalEntry = JournalEntry::factory()->create([
            'company_id' => $this->company->id,
            'status' => 'posted',
        ]);

        JournalEntrySource::factory()->create([
            'journal_entry_id' => $journalEntry->id,
            'source_type' => 'invoice',
            'source_id' => 'invoice-123',
            'source_data' => [
                'invoice_number' => 'INV-2024-001',
                'customer_id' => 'customer-456',
            ],
        ]);

        // Act: Get entry with source information
        $response = $this->actingAs($this->user)
            ->getJson('/api/ledger/journal-entries?include_sources=true');

        // Assert: Source information included
        $response->assertStatus(200);
        $response->assertJsonStructure([
            'data' => [
                '*' => [
                    'id',
                    'description',
                    'status',
                    'sources' => [
                        '*' => [
                            'source_type',
                            'source_id',
                            'source_data',
                        ],
                    ],
                ],
            ],
        ]);
    });

    it('generates trial balance from posted journal entries', function () {
        // Arrange: Create balanced journal entries
        createPostedJournalEntryForTest($this->company, $this->receivablesAccount, $this->revenueAccount, [
            'description' => 'Test transaction 1',
            'amount' => 1000,
        ]);

        createPostedJournalEntryForTest($this->company, $this->receivablesAccount, $this->revenueAccount, [
            'description' => 'Test transaction 2',
            'amount' => 500,
        ]);

        // Act: Generate trial balance
        $response = $this->actingAs($this->user)
            ->getJson('/api/ledger/trial-balance');

        // Assert: Trial balance generated
        $response->assertStatus(200);
        $response->assertJsonStructure([
            'period',
            'generated_at',
            'accounts' => [
                '*' => [
                    'account_id',
                    'account_code',
                    'account_name',
                    'normal_balance',
                    'debit_total',
                    'credit_total',
                    'balance',
                ],
            ],
            'summary' => [
                'total_debits',
                'total_credits',
                'is_balanced',
            ],
        ]);
    });

    it('generates trial balance that is mathematically correct', function () {
        // Arrange: Create transactions affecting multiple accounts
        createPostedJournalEntryForTest($this->company, $this->receivablesAccount, $this->revenueAccount, [
            'description' => 'Revenue recognition',
            'amount' => 5000,
        ]);

        createPostedJournalEntryForTest($this->company, $this->cashAccount, $this->receivablesAccount, [
            'description' => 'Cash receipt',
            'amount' => 3000,
        ]);

        // Act: Generate trial balance
        $response = $this->actingAs($this->user)
            ->getJson('/api/ledger/trial-balance');

        // Assert: Trial balance is mathematically correct
        $response->assertStatus(200);
        $data = $response->json();

        expect($data['summary']['total_debits'])->toBe($data['summary']['total_credits']);
        expect($data['summary']['is_balanced'])->toBe(true);

        // Verify specific account balances
        $accounts = collect($data['accounts'])->keyBy('account_id');

        expect($accounts[$this->cashAccount->id]['balance'])->toBe(3000.00); // Cash increased
        expect($accounts[$this->receivablesAccount->id]['balance'])->toBe(2000.00); // Receivables net increase
        expect($accounts[$this->revenueAccount->id]['balance'])->toBe(-5000.00); // Revenue (credit balance)
    });

    it('generates trial balance for specific date range', function () {
        // Arrange: Create entries with different dates
        $oldEntry = createPostedJournalEntryForTest($this->company, $this->receivablesAccount, $this->revenueAccount, [
            'description' => 'Old transaction',
            'date' => now()->subDays(10),
            'amount' => 1000,
        ]);

        $recentEntry = createPostedJournalEntryForTest($this->company, $this->receivablesAccount, $this->revenueAccount, [
            'description' => 'Recent transaction',
            'date' => now()->subDays(2),
            'amount' => 500,
        ]);

        // Act: Generate trial balance for recent period
        $response = $this->actingAs($this->user)
            ->getJson('/api/ledger/trial-balance?date_from='.now()->subDays(5)->format('Y-m-d'));

        // Assert: Only recent entries included
        $response->assertStatus(200);
        $data = $response->json();

        // Total should only include recent transaction
        expect($data['summary']['total_debits'])->toBe(500.00);
        expect($data['summary']['total_credits'])->toBe(500.00);
    });

    it('excludes draft and approved entries from trial balance', function () {
        // Arrange: Create entries with different statuses
        $draftEntry = createJournalEntryForTest($this->company, ['status' => 'draft']);
        $approvedEntry = createJournalEntryForTest($this->company, ['status' => 'approved']);
        $postedEntry = createPostedJournalEntryForTest($this->company, $this->receivablesAccount, $this->revenueAccount, ['amount' => 2000]);

        // Act: Generate trial balance
        $response = $this->actingAs($this->user)
            ->getJson('/api/ledger/trial-balance');

        // Assert: Only posted entries included
        $response->assertStatus(200);
        $data = $response->json();

        expect($data['summary']['total_debits'])->toBe(2000.00);
        expect($data['summary']['total_credits'])->toBe(2000.00);
    });
});
