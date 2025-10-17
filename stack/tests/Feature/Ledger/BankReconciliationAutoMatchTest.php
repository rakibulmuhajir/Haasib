<?php

use App\Models\BankReconciliation;
use App\Models\BankReconciliationMatch;
use App\Models\BankStatementLine;
use App\Models\ChartOfAccount;
use App\Models\Company;
use App\Models\Invoice;
use App\Models\JournalEntry;
use App\Models\Payment;
use App\Models\User;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Event;
use Modules\Ledger\Actions\BankReconciliation\RunAutoMatch;
use Modules\Ledger\Services\BankReconciliationMatchingService;

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
    ]);

    // Create statement lines
    $this->statementLines = BankStatementLine::factory()->count(5)->create([
        'statement_id' => $this->reconciliation->statement_id,
        'company_id' => $this->company->id,
    ]);

    // Create internal transactions to match against
    $this->payments = Payment::factory()->count(3)->create([
        'company_id' => $this->company->id,
        'amount' => 1000,
    ]);

    $this->invoices = Invoice::factory()->count(2)->create([
        'company_id' => $this->company->id,
        'total' => 1500,
    ]);
});

it('runs auto-matching on reconciliation statement lines', function () {
    Bus::fake();

    $response = $this->postJson(route('bank-reconciliation.reconciliations.auto-match', $this->reconciliation->id));

    $response->assertStatus(202);
    $response->assertJsonFragment([
        'message' => 'Auto-matching job queued successfully',
    ]);

    Bus::assertDispatched(\Modules\Ledger\Jobs\RunAutoMatchJob::class, function ($job) use ($this->reconciliation) {
        return $job->reconciliation->id === $this->reconciliation->id;
    });
});

it('creates matches with high confidence for exact amount and date matches', function () {
    // Create statement line that exactly matches a payment
    $statementLine = BankStatementLine::factory()->create([
        'statement_id' => $this->reconciliation->statement_id,
        'company_id' => $this->company->id,
        'amount' => 1000,
        'transaction_date' => now()->subDays(5),
        'description' => 'Payment from Customer ABC',
        'reference_number' => 'PAY-001',
    ]);

    // Create matching payment
    $payment = Payment::factory()->create([
        'company_id' => $this->company->id,
        'amount' => 1000,
        'payment_date' => now()->subDays(5),
        'reference' => 'PAY-001',
    ]);

    $service = new BankReconciliationMatchingService();
    $matches = $service->runAutoMatch($this->reconciliation);

    expect($matches)->toHaveCount(1);
    expect($matches->first()->statement_line_id)->toBe($statementLine->id);
    expect($matches->first()->source_id)->toBe($payment->id);
    expect($matches->first()->source_type)->toBe('acct.payment');
    expect($matches->first()->auto_matched)->toBeTrue();
    expect($matches->first()->confidence_score)->toBeGreaterThanOrEqual(0.9);
});

it('creates matches with medium confidence for amount-only matches', function () {
    $statementLine = BankStatementLine::factory()->create([
        'statement_id' => $this->reconciliation->statement_id,
        'company_id' => $this->company->id,
        'amount' => 1500,
        'transaction_date' => now()->subDays(10),
        'description' => 'Invoice payment',
    ]);

    $invoice = Invoice::factory()->create([
        'company_id' => $this->company->id,
        'total' => 1500,
        'invoice_date' => now()->subDays(15),
    ]);

    $service = new BankReconciliationMatchingService();
    $matches = $service->runAutoMatch($this->reconciliation);

    expect($matches)->toHaveCount(1);
    expect($matches->first()->confidence_score)->toBeGreaterThanOrEqual(0.7);
    expect($matches->first()->confidence_score)->toBeLessThan(0.9);
});

it('does not create matches for low confidence candidates', function () {
    $statementLine = BankStatementLine::factory()->create([
        'statement_id' => $this->reconciliation->statement_id,
        'company_id' => $this->company->id,
        'amount' => 100,
        'transaction_date' => now()->subDays(30),
        'description' => 'Random transaction',
    ]);

    $payment = Payment::factory()->create([
        'company_id' => $this->company->id,
        'amount' => 100,
        'payment_date' => now()->subMonths(2), // Very different date
    ]);

    $service = new BankReconciliationMatchingService();
    $matches = $service->runAutoMatch($this->reconciliation);

    expect($matches)->toHaveCount(0); // Should not match due to low confidence
});

it('prevents duplicate matches for the same statement line', function () {
    $statementLine = BankStatementLine::factory()->create([
        'statement_id' => $this->reconciliation->statement_id,
        'company_id' => $this->company->id,
        'amount' => 1000,
    ]);

    $payment = Payment::factory()->create([
        'company_id' => $this->company->id,
        'amount' => 1000,
    ]);

    // Create an existing match
    BankReconciliationMatch::factory()->create([
        'reconciliation_id' => $this->reconciliation->id,
        'statement_line_id' => $statementLine->id,
        'source_type' => 'acct.payment',
        'source_id' => $payment->id,
    ]);

    $service = new BankReconciliationMatchingService();
    $matches = $service->runAutoMatch($this->reconciliation);

    expect($matches)->toHaveCount(0); // Should not create duplicate match
});

it('allows manual override of auto-matches', function () {
    $statementLine = BankStatementLine::factory()->create([
        'statement_id' => $this->reconciliation->statement_id,
        'company_id' => $this->company->id,
        'amount' => 1000,
    ]);

    $wrongPayment = Payment::factory()->create([
        'company_id' => $this->company->id,
        'amount' => 1000,
    ]);

    $correctPayment = Payment::factory()->create([
        'company_id' => $this->company->id,
        'amount' => 1000,
    ]);

    // Create auto-match to wrong payment
    $autoMatch = BankReconciliationMatch::factory()->create([
        'reconciliation_id' => $this->reconciliation->id,
        'statement_line_id' => $statementLine->id,
        'source_type' => 'acct.payment',
        'source_id' => $wrongPayment->id,
        'auto_matched' => true,
    ]);

    // Create manual match to correct payment
    $response = $this->postJson(route('bank-reconciliation.reconciliations.matches.store', $this->reconciliation->id), [
        'statement_line_id' => $statementLine->id,
        'source_type' => 'acct.payment',
        'source_id' => $correctPayment->id,
        'amount' => 1000,
    ]);

    $response->assertStatus(201);

    // Verify old auto-match is removed and new manual match exists
    expect(BankReconciliationMatch::where('statement_line_id', $statementLine->id)->count())->toBe(1);
    
    $newMatch = BankReconciliationMatch::where('statement_line_id', $statementLine->id)->first();
    expect($newMatch->source_id)->toBe($correctPayment->id);
    expect($newMatch->auto_matched)->toBeFalse();
});

it('validates manual match data', function () {
    $statementLine = BankStatementLine::factory()->create([
        'statement_id' => $this->reconciliation->statement_id,
        'company_id' => $this->company->id,
        'amount' => 1000,
    ]);

    $response = $this->postJson(route('bank-reconciliation.reconciliations.matches.store', $this->reconciliation->id), [
        'statement_line_id' => $statementLine->id,
        'source_type' => 'acct.payment',
        'source_id' => 'invalid-uuid',
        'amount' => 1000,
    ]);

    $response->assertStatus(422);
    $response->assertJsonValidationErrors(['source_id']);
});

it('allows removal of matches', function () {
    $statementLine = BankStatementLine::factory()->create([
        'statement_id' => $this->reconciliation->statement_id,
        'company_id' => $this->company->id,
        'amount' => 1000,
    ]);

    $payment = Payment::factory()->create([
        'company_id' => $this->company->id,
        'amount' => 1000,
    ]);

    $match = BankReconciliationMatch::factory()->create([
        'reconciliation_id' => $this->reconciliation->id,
        'statement_line_id' => $statementLine->id,
        'source_type' => 'acct.payment',
        'source_id' => $payment->id,
    ]);

    $response = $this->deleteJson(route('bank-reconciliation.reconciliations.matches.destroy', [
        'reconciliation' => $this->reconciliation->id,
        'match' => $match->id,
    ]));

    $response->assertStatus(204);

    expect(BankReconciliationMatch::find($match->id))->toBeNull();
});

it('updates reconciliation variance after matches', function () {
    // Create scenario where variance should be reduced by matching
    $statementLine = BankStatementLine::factory()->create([
        'statement_id' => $this->reconciliation->statement_id,
        'company_id' => $this->company->id,
        'amount' => 1000,
    ]);

    $payment = Payment::factory()->create([
        'company_id' => $this->company->id,
        'amount' => 1000,
    ]);

    // Initially, variance should be the full statement amount
    $initialVariance = $this->reconciliation->variance;
    expect($initialVariance)->toBe(1000.0);

    // Create a match
    BankReconciliationMatch::factory()->create([
        'reconciliation_id' => $this->reconciliation->id,
        'statement_line_id' => $statementLine->id,
        'source_type' => 'acct.payment',
        'source_id' => $payment->id,
        'amount' => 1000,
    ]);

    // Refresh reconciliation to see updated variance
    $this->reconciliation->refresh();
    expect($this->reconciliation->variance)->toBe(0.0);
});

it('emits match events for real-time updates', function () {
    Event::fake();

    $statementLine = BankStatementLine::factory()->create([
        'statement_id' => $this->reconciliation->statement_id,
        'company_id' => $this->company->id,
        'amount' => 1000,
    ]);

    $payment = Payment::factory()->create([
        'company_id' => $this->company->id,
        'amount' => 1000,
    ]);

    $response = $this->postJson(route('bank-reconciliation.reconciliations.matches.store', $this->reconciliation->id), [
        'statement_line_id' => $statementLine->id,
        'source_type' => 'acct.payment',
        'source_id' => $payment->id,
        'amount' => 1000,
    ]);

    $response->assertStatus(201);

    Event::assertDispatched(\Modules\Ledger\Events\BankReconciliationMatched::class, function ($event) use ($this->reconciliation) {
        return $event->reconciliation->id === $this->reconciliation->id;
    });
});

it('prevents unauthorized access to matching operations', function () {
    $otherUser = User::factory()->create();
    $this->actingAs($otherUser);

    $statementLine = BankStatementLine::factory()->create([
        'statement_id' => $this->reconciliation->statement_id,
        'company_id' => $this->company->id,
        'amount' => 1000,
    ]);

    $response = $this->postJson(route('bank-reconciliation.reconciliations.matches.store', $this->reconciliation->id), [
        'statement_line_id' => $statementLine->id,
        'source_type' => 'acct.payment',
        'source_id' => 'some-uuid',
        'amount' => 1000,
    ]);

    $response->assertStatus(403);
});