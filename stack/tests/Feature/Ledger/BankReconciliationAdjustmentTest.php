<?php

use App\Models\BankReconciliation;
use App\Models\BankReconciliationAdjustment;
use App\Models\BankStatementLine;
use App\Models\ChartOfAccount;
use App\Models\Company;
use App\Models\JournalEntry;
use App\Models\User;
use Illuminate\Support\Facades\Event;

beforeEach(function () {
    $this->company = Company::factory()->create();
    $this->user = User::factory()->create(['current_company_id' => $this->company->id]);
    $this->actingAs($this->user);

    $this->bankAccount = ChartOfAccount::factory()->create([
        'company_id' => $this->company->id,
        'account_type' => 'asset',
        'account_subtype' => 'bank',
    ]);

    $this->reconciliation = BankReconciliation::factory()->create([
        'company_id' => $this->company->id,
        'ledger_account_id' => $this->bankAccount->id,
        'status' => 'in_progress',
        'variance' => 50.00, // $50 variance to resolve
    ]);

    // Set up default adjustment accounts
    $this->bankFeeExpenseAccount = ChartOfAccount::factory()->create([
        'company_id' => $this->company->id,
        'account_type' => 'expense',
        'account_subtype' => 'bank_fee',
    ]);

    $this->interestIncomeAccount = ChartOfAccount::factory()->create([
        'company_id' => $this->company->id,
        'account_type' => 'revenue',
        'account_subtype' => 'interest_income',
    ]);
});

it('can create a bank fee adjustment', function () {
    $response = $this->postJson(route('bank-reconciliation.reconciliations.adjustments.store', $this->reconciliation->id), [
        'adjustment_type' => 'bank_fee',
        'amount' => 25.00,
        'description' => 'Monthly maintenance fee',
        'post_journal_entry' => true,
        'statement_line_id' => null,
    ]);

    $response->assertStatus(201);

    $adjustment = BankReconciliationAdjustment::first();
    expect($adjustment)->not->toBeNull();
    expect($adjustment->adjustment_type)->toBe('bank_fee');
    expect($adjustment->amount)->toBe(-25.00); // Bank fees should be negative
    expect($adjustment->description)->toBe('Monthly maintenance fee');
    expect($adjustment->reconciliation_id)->toBe($this->reconciliation->id);
    expect($adjustment->company_id)->toBe($this->company->id);

    // Verify journal entry was created
    expect($adjustment->journal_entry_id)->not->toBeNull();
    $journalEntry = JournalEntry::find($adjustment->journal_entry_id);
    expect($journalEntry)->not->toBeNull();
    expect($journalEntry->description)->toBe('Monthly maintenance fee');
});

it('can create an interest income adjustment', function () {
    $response = $this->postJson(route('bank-reconciliation.reconciliations.adjustments.store', $this->reconciliation->id), [
        'adjustment_type' => 'interest',
        'amount' => 15.50,
        'description' => 'Monthly interest earned',
        'post_journal_entry' => true,
    ]);

    $response->assertStatus(201);

    $adjustment = BankReconciliationAdjustment::first();
    expect($adjustment->adjustment_type)->toBe('interest');
    expect($adjustment->amount)->toBe(15.50); // Interest should be positive
    expect($adjustment->description)->toBe('Monthly interest earned');

    // Verify journal entry was created with proper debit/credit
    expect($adjustment->journal_entry_id)->not->toBeNull();
    $journalEntry = JournalEntry::find($adjustment->journal_entry_id);
    expect($journalEntry->transactions)->toHaveCount(2);
});

it('can create a write-off adjustment', function () {
    $response = $this->postJson(route('bank-reconciliation.reconciliations.adjustments.store', $this->reconciliation->id), [
        'adjustment_type' => 'write_off',
        'amount' => 10.00,
        'description' => 'Uncleared check write-off',
        'post_journal_entry' => true,
    ]);

    $response->assertStatus(201);

    $adjustment = BankReconciliationAdjustment::first();
    expect($adjustment->adjustment_type)->toBe('write_off');
    expect($adjustment->amount)->toBe(-10.00); // Write-offs should be negative
    expect($adjustment->description)->toBe('Uncleared check write-off');
});

it('can create a timing adjustment', function () {
    $statementLine = BankStatementLine::factory()->create([
        'statement_id' => $this->reconciliation->statement_id,
        'company_id' => $this->company->id,
        'amount' => 100.00,
    ]);

    $response = $this->postJson(route('bank-reconciliation.reconciliations.adjustments.store', $this->reconciliation->id), [
        'adjustment_type' => 'timing',
        'amount' => 100.00,
        'description' => 'Timing difference for deposit',
        'post_journal_entry' => true,
        'statement_line_id' => $statementLine->id,
    ]);

    $response->assertStatus(201);

    $adjustment = BankReconciliationAdjustment::first();
    expect($adjustment->adjustment_type)->toBe('timing');
    expect($adjustment->statement_line_id)->toBe($statementLine->id);
    expect($adjustment->amount)->toBe(100.00); // Timing can be positive or negative
});

it('validates adjustment amount sign based on type', function () {
    // Bank fee with positive amount should fail
    $response = $this->postJson(route('bank-reconciliation.reconciliations.adjustments.store', $this->reconciliation->id), [
        'adjustment_type' => 'bank_fee',
        'amount' => 25.00, // Should be negative for bank fees
        'description' => 'Invalid bank fee',
        'post_journal_entry' => false,
    ]);

    $response->assertStatus(422);
    $response->assertJsonValidationErrors(['amount']);

    // Interest with negative amount should fail
    $response = $this->postJson(route('bank-reconciliation.reconciliations.adjustments.store', $this->reconciliation->id), [
        'adjustment_type' => 'interest',
        'amount' => -15.50, // Should be positive for interest
        'description' => 'Invalid interest',
        'post_journal_entry' => false,
    ]);

    $response->assertStatus(422);
    $response->assertJsonValidationErrors(['amount']);
});

it('updates reconciliation variance after adjustment', function () {
    // Initial variance is 50.00
    expect($this->reconciliation->variance)->toBe(50.00);

    // Create a bank fee adjustment of -25.00
    $this->postJson(route('bank-reconciliation.reconciliations.adjustments.store', $this->reconciliation->id), [
        'adjustment_type' => 'bank_fee',
        'amount' => 25.00,
        'description' => 'Bank fee',
        'post_journal_entry' => true,
    ]);

    // Refresh reconciliation to see updated variance
    $this->reconciliation->refresh();
    expect($this->reconciliation->variance)->toBe(25.00); // 50.00 - 25.00 = 25.00
});

it('can create adjustment without posting journal entry', function () {
    $response = $this->postJson(route('bank-reconciliation.reconciliations.adjustments.store', $this->reconciliation->id), [
        'adjustment_type' => 'bank_fee',
        'amount' => 20.00,
        'description' => 'Manual adjustment',
        'post_journal_entry' => false,
    ]);

    $response->assertStatus(201);

    $adjustment = BankReconciliationAdjustment::first();
    expect($adjustment->journal_entry_id)->toBeNull();
});

it('emits adjustment events for real-time updates', function () {
    Event::fake();

    $response = $this->postJson(route('bank-reconciliation.reconciliations.adjustments.store', $this->reconciliation->id), [
        'adjustment_type' => 'bank_fee',
        'amount' => 15.00,
        'description' => 'Event test adjustment',
        'post_journal_entry' => true,
    ]);

    $response->assertStatus(201);

    Event::assertDispatched(\Modules\Ledger\Events\BankReconciliationAdjustmentCreated::class);
});

it('prevents adjustments on completed reconciliations', function () {
    $this->reconciliation->update(['status' => 'completed']);

    $response = $this->postJson(route('bank-reconciliation.reconciliations.adjustments.store', $this->reconciliation->id), [
        'adjustment_type' => 'bank_fee',
        'amount' => 10.00,
        'description' => 'Should fail',
        'post_journal_entry' => false,
    ]);

    $response->assertStatus(422);
    $response->assertJsonFragment([
        'message' => 'Adjustments can only be made on active reconciliations',
    ]);
});

it('prevents unauthorized access to adjustments', function () {
    $otherUser = User::factory()->create();
    $this->actingAs($otherUser);

    $response = $this->postJson(route('bank-reconciliation.reconciliations.adjustments.store', $this->reconciliation->id), [
        'adjustment_type' => 'bank_fee',
        'amount' => 10.00,
        'description' => 'Unauthorized',
        'post_journal_entry' => false,
    ]);

    $response->assertStatus(403);
});

it('can delete adjustments', function () {
    // Create an adjustment first
    $adjustment = BankReconciliationAdjustment::factory()->create([
        'reconciliation_id' => $this->reconciliation->id,
        'company_id' => $this->company->id,
        'adjustment_type' => 'bank_fee',
        'amount' => -10.00,
        'description' => 'To be deleted',
    ]);

    $response = $this->deleteJson(route('bank-reconciliation.reconciliations.adjustments.destroy', [
        'reconciliation' => $this->reconciliation->id,
        'adjustment' => $adjustment->id,
    ]));

    $response->assertStatus(204);
    expect(BankReconciliationAdjustment::find($adjustment->id))->toBeNull();
});

it('can update adjustments', function () {
    $adjustment = BankReconciliationAdjustment::factory()->create([
        'reconciliation_id' => $this->reconciliation->id,
        'company_id' => $this->company->id,
        'adjustment_type' => 'bank_fee',
        'amount' => -10.00,
        'description' => 'Original description',
    ]);

    $response = $this->putJson(route('bank-reconciliation.reconciliations.adjustments.update', [
        'reconciliation' => $this->reconciliation->id,
        'adjustment' => $adjustment->id,
    ]), [
        'amount' => 15.00,
        'description' => 'Updated description',
    ]);

    $response->assertStatus(200);

    $adjustment->refresh();
    expect($adjustment->amount)->toBe(-15.00);
    expect($adjustment->description)->toBe('Updated description');
});

it('validates adjustment data', function () {
    $response = $this->postJson(route('bank-reconciliation.reconciliations.adjustments.store', $this->reconciliation->id), [
        'adjustment_type' => 'invalid_type',
        'amount' => 'not_a_number',
        'description' => '',
        'post_journal_entry' => true,
    ]);

    $response->assertStatus(422);
    $response->assertJsonValidationErrors([
        'adjustment_type',
        'amount',
        'description',
    ]);
});

it('uses correct default accounts based on adjustment type', function () {
    // This test verifies that the adjustment service uses appropriate default accounts
    // for different adjustment types when creating journal entries

    $response = $this->postJson(route('bank-reconciliation.reconciliations.adjustments.store', $this->reconciliation->id), [
        'adjustment_type' => 'bank_fee',
        'amount' => 25.00,
        'description' => 'Test bank fee',
        'post_journal_entry' => true,
    ]);

    $response->assertStatus(201);

    $adjustment = BankReconciliationAdjustment::first();
    $journalEntry = $adjustment->journalEntry;

    // Verify the journal entry has the correct debit/credit structure
    // For bank fees: Debit expense account, Credit bank account
    $transactions = $journalEntry->transactions;

    $debitTransaction = $transactions->firstWhere('debit_amount', '>', 0);
    $creditTransaction = $transactions->firstWhere('credit_amount', '>', 0);

    expect($debitTransaction)->not->toBeNull();
    expect($creditTransaction)->not->toBeNull();
    expect($debitTransaction->debit_amount)->toBe(25.00);
    expect($creditTransaction->credit_amount)->toBe(25.00);
});
