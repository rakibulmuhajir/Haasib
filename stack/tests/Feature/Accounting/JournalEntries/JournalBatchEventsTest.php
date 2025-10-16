<?php

use App\Models\Account;
use App\Models\JournalBatch;
use App\Models\JournalEntry;
use App\Models\JournalTransaction;
use Illuminate\Support\Facades\Event;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Accounting\Domain\JournalEntries\Events\BatchCreated;
use Modules\Accounting\Domain\JournalEntries\Events\BatchApproved;
use Modules\Accounting\Domain\JournalEntries\Events\BatchPosted;
use Modules\Accounting\Domain\JournalEntries\Events\BatchDeleted;
use Modules\Accounting\Domain\JournalEntries\Events\EntryAddedToBatch;
use Modules\Accounting\Domain\JournalEntries\Events\EntryRemovedFromBatch;

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

uses(RefreshDatabase::class);

// Helper functions for this test file
function createDraftJournalEntryForTest($company, $overrides = []): JournalEntry
{
    $data = array_merge([
        'company_id' => $company->id,
        'status' => 'draft',
        'date' => now(),
        'description' => 'Test Journal Entry',
    ], $overrides);

    $entry = JournalEntry::factory()->create($data);

    // Create transactions
    JournalTransaction::factory()->create([
        'journal_entry_id' => $entry->id,
        'account_id' => Account::where('company_id', $company->id)->first()->id,
        'debit_credit' => 'debit',
        'amount' => 1000,
    ]);

    JournalTransaction::factory()->create([
        'journal_entry_id' => $entry->id,
        'account_id' => Account::where('company_id', $company->id)->skip(1)->first()->id,
        'debit_credit' => 'credit',
        'amount' => 1000,
    ]);

    return $entry;
}

function createJournalBatchForTest($company, $overrides = []): JournalBatch
{
    return JournalBatch::factory()->create(array_merge([
        'company_id' => $company->id,
        'status' => 'pending',
        'name' => 'Test Batch',
        'total_entries' => 0,
    ], $overrides));
}

it('fires batch created event when batch is created', function () {
    Event::fake();

    $this->actingAs($this->user);

    $entries = [
        createDraftJournalEntryForTest($this->company, ['description' => 'Test Entry 1']),
        createDraftJournalEntryForTest($this->company, ['description' => 'Test Entry 2']),
    ];

    $batchData = [
        'name' => 'Test Batch',
        'description' => 'Test batch description',
        'journal_entry_ids' => $entries->pluck('id')->toArray(),
    ];

    $response = $this->postJson('/api/ledger/journal-batches', $batchData);

    $response->assertStatus(201);
    Event::assertDispatched(BatchCreated::class, function ($event) {
        return $event->createdBy === $this->user->id;
    });
});

it('fires batch approved event when batch is approved', function () {
    Event::fake();

    $this->actingAs($this->user);

    $batch = createJournalBatchForTest($this->company, ['status' => 'pending']);

    $response = $this->postJson("/api/ledger/journal-batches/{$batch->id}/approve");

    $response->assertStatus(200);
    Event::assertDispatched(BatchApproved::class, function ($event) {
        return $event->approvedBy === $this->user->id;
    });
});

it('fires batch posted event when batch is posted', function () {
    Event::fake();

    $this->actingAs($this->user);

    $batch = createJournalBatchForTest($this->company, ['status' => 'approved']);

    $response = $this->postJson("/api/ledger/journal-batches/{$batch->id}/post");

    $response->assertStatus(200);
    Event::assertDispatched(BatchPosted::class, function ($event) {
        return $event->postedBy === $this->user->id;
    });
});

it('fires batch deleted event when batch is deleted', function () {
    Event::fake();

    $this->actingAs($this->user);

    $batch = createJournalBatchForTest($this->company, ['status' => 'pending']);

    $response = $this->deleteJson("/api/ledger/journal-batches/{$batch->id}");

    $response->assertStatus(200);
    Event::assertDispatched(BatchDeleted::class, function ($event) {
        return $event->deletedBy === $this->user->id;
    });
});

it('fires entry added to batch event when entries are added', function () {
    Event::fake();

    $this->actingAs($this->user);

    $batch = createJournalBatchForTest($this->company, ['status' => 'pending']);
    $entry = createDraftJournalEntryForTest($this->company, ['description' => 'Test Entry']);

    $response = $this->postJson("/api/ledger/journal-batches/{$batch->id}/add-entries", [
        'journal_entry_ids' => [$entry->id],
    ]);

    $response->assertStatus(200);
    Event::assertDispatched(EntryAddedToBatch::class, function ($event) use ($batch, $entry) {
        return $event->batch->id === $batch->id
            && $event->entry->id === $entry->id
            && $event->addedBy === $this->user->id;
    });
});

it('fires entry removed from batch event when entries are removed', function () {
    Event::fake();

    $this->actingAs($this->user);

    $entry = createDraftJournalEntryForTest($this->company, ['description' => 'Test Entry']);
    $batch = createJournalBatchForTest($this->company, ['status' => 'pending']);

    // Add entry to batch first
    $batch->journalEntries()->attach($entry->id);
    $batch->update(['total_entries' => 1]);

    $response = $this->postJson("/api/ledger/journal-batches/{$batch->id}/remove-entries", [
        'journal_entry_ids' => [$entry->id],
    ]);

    $response->assertStatus(200);
    Event::assertDispatched(EntryRemovedFromBatch::class, function ($event) use ($batch, $entry) {
        return $event->batch->id === $batch->id
            && $event->entry->id === $entry->id
            && $event->removedBy === $this->user->id;
    });
});