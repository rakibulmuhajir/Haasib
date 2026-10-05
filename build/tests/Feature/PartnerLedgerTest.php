<?php

use App\Models\Partner;
use App\Models\PartnerTransaction;
use App\Modules\Accounting\Models\Account;
use App\Modules\Accounting\Models\JournalEntry;
use App\Modules\Accounting\Models\Transaction;
use App\Modules\Accounting\Services\GlPostingService;
use App\Modules\Accounting\Services\PartnerStatementService;
use App\Modules\FuelStation\Services\DailyCloseReopenService;
use App\Modules\FuelStation\Services\ProfitStatementService;
use App\Services\CommandBus;
use App\Services\CompanyContextService;
use App\Services\CompanyRbacBootstrapper;
use App\Services\CurrentCompany;
use App\Services\PartnerLedgerService;
use App\Services\PartnerProfitShareService;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

require_once __DIR__.'/FuelStation/CreditCloseFixtures.php';

/*
 * Partner money goes through the books: each partner's own Capital and Drawings accounts,
 * the page forms, the Daily Close, the drawing-limit warning, the monthly profit share and
 * the partner statement.
 */

/** The credit-close company with an expense account, an owner who can use the pages, and the fuel module. */
function partnerLedgerFixture(bool $fuel = true): array
{
    $f = creditCloseFixture();
    $f['accounts']['6180'] = Account::create([
        'company_id' => $f['company']->id, 'code' => '6180', 'name' => 'Misc expense',
        'type' => 'expense', 'subtype' => 'operating_expense', 'normal_balance' => 'debit', 'is_active' => true,
    ]);

    DB::select("SELECT set_config('app.is_super_admin', 'true', false)");
    app(CompanyRbacBootstrapper::class)->bootstrap($f['company']);
    DB::table('auth.company_user')->insert([
        'company_id' => $f['company']->id, 'user_id' => $f['user']->id, 'role' => 'owner',
        'joined_at' => now(), 'is_active' => true, 'created_at' => now(), 'updated_at' => now(),
    ]);
    app(CompanyContextService::class)->withContext($f['company'], fn () => app(CompanyContextService::class)->assignRole($f['user'], 'owner'));
    if ($fuel) {
        $f['company']->enableModule('fuel_station');
    }
    DB::select("SELECT set_config('app.is_super_admin', 'false', false)");
    test()->actingAs($f['user']);
    app(CurrentCompany::class)->set($f['company']);

    return $f;
}

function partnerLedgerMake(array $f, string $name, float $share = 0, string $limitPeriod = 'none', ?float $limit = null): Partner
{
    $partner = Partner::create([
        'company_id' => $f['company']->id, 'name' => $name, 'profit_share_percentage' => $share,
        'drawing_limit_period' => $limitPeriod, 'drawing_limit_amount' => $limit, 'is_active' => true,
    ]);
    app(PartnerLedgerService::class)->accountsFor($partner);

    return $partner->refresh();
}

/** Credit-side balance of one account (capital, retained earnings) or debit-side (drawings, cash). */
function partnerLedgerNet(array $f, string $accountId, string $normal = 'credit'): float
{
    return app(PartnerLedgerService::class)->accountNet($f['company']->id, $accountId, $normal);
}

/** A plain balanced journal: Dr $debit, Cr $credit. */
function partnerLedgerJournal(array $f, string $date, string $debit, string $credit, float $amount): Transaction
{
    return app(GlPostingService::class)->postBalancedTransaction([
        'company_id' => $f['company']->id, 'transaction_type' => 'journal', 'date' => $date,
        'currency' => 'PKR', 'base_currency' => 'PKR', 'description' => 'Test journal',
    ], [
        ['account_id' => $debit, 'type' => 'debit', 'amount' => $amount],
        ['account_id' => $credit, 'type' => 'credit', 'amount' => $amount],
    ]);
}

function partnerLedgerRun(array $f, string $command, array $params): array
{
    return app(CompanyContextService::class)->withContext($f['company'], fn () => app(CommandBus::class)->dispatch($command, $params, $f['user']));
}

/** A close that carries partner deposits / withdrawals; closing cash follows them. */
function partnerLedgerClose(array $f, array $deposits = [], array $withdrawals = []): array
{
    $f['payload']['partner_deposits'] = $deposits;
    $f['payload']['partner_withdrawals'] = $withdrawals;
    $f['payload']['closing_cash'] = 25000 + array_sum(array_column($deposits, 'amount')) - array_sum(array_column($withdrawals, 'amount'));

    return app(\App\Modules\FuelStation\Services\DailyCloseService::class)->processDailyClose($f['company']->id, $f['payload'], $f['user']);
}

test('a partner created on the page gets their own Capital and Drawings accounts', function () {
    $f = partnerLedgerFixture();

    test()->post("/{$f['company']->slug}/partners", [
        'name' => 'Ali', 'profit_share_percentage' => 50, 'drawing_limit_period' => 'none',
    ])->assertRedirect();

    $partner = Partner::where('company_id', $f['company']->id)->where('name', 'Ali')->firstOrFail();
    $capital = Account::find($partner->capital_account_id);
    $drawings = Account::find($partner->drawing_account_id);

    expect($capital)->not->toBeNull()
        ->and($capital->name)->toBe("Capital \u{2013} Ali")
        ->and($capital->type)->toBe('equity')->and($capital->normal_balance)->toBe('credit')->and($capital->is_system)->toBeFalse()
        ->and((int) $capital->code)->toBeBetween(3300, 3499)
        ->and($drawings->name)->toBe("Drawings \u{2013} Ali")
        ->and($drawings->type)->toBe('equity')->and($drawings->normal_balance)->toBe('debit')->and($drawings->is_contra)->toBeTrue()
        ->and((int) $drawings->code)->toBeBetween(3500, 3699);

    // A partner made without them gets them lazily, and a second partner never collides.
    $bare = Partner::create(['company_id' => $f['company']->id, 'name' => 'Bilal', 'profit_share_percentage' => 10, 'drawing_limit_period' => 'none']);
    expect($bare->capital_account_id)->toBeNull();
    $made = app(PartnerLedgerService::class)->accountsFor($bare);
    expect($made['capital']->code)->not->toBe($capital->code)->and($made['drawings']->code)->not->toBe($drawings->code);
});

test('investing and withdrawing from the page post the right journals and totals', function () {
    $f = partnerLedgerFixture();
    $partner = partnerLedgerMake($f, 'Ali');
    $capitalId = $partner->capital_account_id;
    $drawingsId = $partner->drawing_account_id;

    test()->post("/{$f['company']->slug}/partners/{$partner->id}/invest", [
        'amount' => 50000, 'transaction_date' => '2026-09-10', 'account_id' => $f['accounts']['1050']->id, 'description' => 'Top up',
    ])->assertRedirect()->assertSessionHas('success');
    test()->post("/{$f['company']->slug}/partners/{$partner->id}/withdraw", [
        'amount' => 10000, 'transaction_date' => '2026-09-12', 'account_id' => $f['accounts']['1020']->id,
    ])->assertRedirect()->assertSessionHas('success');

    $invest = PartnerTransaction::where('partner_id', $partner->id)->where('transaction_type', 'investment')->sole();
    $withdraw = PartnerTransaction::where('partner_id', $partner->id)->where('transaction_type', 'withdrawal')->sole();
    $lines = fn ($txnId) => JournalEntry::where('transaction_id', $txnId)->get()->map(fn ($e) => [$e->account_id, (float) $e->debit_amount, (float) $e->credit_amount])->all();

    expect($lines($invest->gl_transaction_id))->toContain([$f['accounts']['1050']->id, 50000.0, 0.0])->toContain([$capitalId, 0.0, 50000.0])
        ->and($lines($withdraw->gl_transaction_id))->toContain([$drawingsId, 10000.0, 0.0])->toContain([$f['accounts']['1020']->id, 0.0, 10000.0])
        ->and($invest->journal_entry_id)->not->toBeNull()
        ->and($invest->payment_method)->toBe('cash')->and($withdraw->payment_method)->toBe('bank_transfer');

    $partner->refresh();
    expect((float) $partner->total_invested)->toBe(50000.0)   // counted once, not twice
        ->and((float) $partner->total_withdrawn)->toBe(10000.0)
        ->and($partner->net_capital)->toBe(40000.0)
        ->and(partnerLedgerNet($f, $capitalId))->toBe(50000.0)
        ->and(partnerLedgerNet($f, $drawingsId, 'debit'))->toBe(10000.0);
});

test('the invest command refuses a non cash or bank account and a bad amount', function () {
    $f = partnerLedgerFixture();
    $partner = partnerLedgerMake($f, 'Ali');

    expect(fn () => partnerLedgerRun($f, 'partner.invest', [
        'partner_id' => $partner->id, 'amount' => 100, 'transaction_date' => '2026-09-10', 'account_id' => $f['accounts']['4100']->id,
    ]))->toThrow(ValidationException::class);
    expect(fn () => partnerLedgerRun($f, 'partner.withdraw', [
        'partner_id' => $partner->id, 'amount' => 0, 'transaction_date' => '2026-09-10', 'account_id' => $f['accounts']['1050']->id,
    ]))->toThrow(ValidationException::class);
    expect(PartnerTransaction::where('partner_id', $partner->id)->count())->toBe(0);
});

test('close deposits and withdrawals post to each partner own accounts and bump the totals', function () {
    $f = partnerLedgerFixture();
    $ali = partnerLedgerMake($f, 'Ali');
    $bilal = partnerLedgerMake($f, 'Bilal');

    $posted = partnerLedgerClose($f, [['partner_id' => $ali->id, 'amount' => 5000]], [['partner_id' => $bilal->id, 'amount' => 2000]]);
    $closeId = $posted['transaction_id'];
    $entries = JournalEntry::where('transaction_id', $closeId)->get();
    $line = fn ($accountId) => $entries->firstWhere('account_id', $accountId);

    expect((float) $line($ali->capital_account_id)->credit_amount)->toBe(5000.0)
        ->and((float) $line($bilal->drawing_account_id)->debit_amount)->toBe(2000.0)
        ->and(Account::where('company_id', $f['company']->id)->whereIn('code', ['2210'])->exists())->toBeFalse() // no shared account involved
        ->and($posted['warnings'])->toBe([]);

    $dep = PartnerTransaction::where('partner_id', $ali->id)->sole();
    $wd = PartnerTransaction::where('partner_id', $bilal->id)->sole();
    expect($dep->gl_transaction_id)->toBe($closeId)->and($dep->journal_entry_id)->toBe($line($ali->capital_account_id)->id)
        ->and($wd->gl_transaction_id)->toBe($closeId)->and($wd->journal_entry_id)->toBe($line($bilal->drawing_account_id)->id)
        ->and((float) $ali->refresh()->total_invested)->toBe(5000.0)
        ->and((float) $bilal->refresh()->total_withdrawn)->toBe(2000.0)
        ->and($ali->net_capital)->toBe(5000.0)->and($bilal->net_capital)->toBe(-2000.0);
});

test('reopening a close undoes its partner deposits and withdrawals and their totals', function () {
    $f = partnerLedgerFixture();
    $ali = partnerLedgerMake($f, 'Ali', 0, 'monthly', 10000);
    $posted = partnerLedgerClose($f, [['partner_id' => $ali->id, 'amount' => 5000]], [['partner_id' => $ali->id, 'amount' => 2000]]);
    expect((float) $ali->refresh()->total_invested)->toBe(5000.0)->and((float) $ali->total_withdrawn)->toBe(2000.0);

    app(DailyCloseReopenService::class)->reopen(Transaction::findOrFail($posted['transaction_id']), $f['user'], 'Cash count was wrong.');

    $ali->refresh();
    expect(PartnerTransaction::where('partner_id', $ali->id)->count())->toBe(0)
        ->and((float) $ali->total_invested)->toBe(0.0)->and((float) $ali->total_withdrawn)->toBe(0.0)
        ->and($ali->withdrawnThisPeriod('2026-09-15'))->toBe(0.0)
        ->and(partnerLedgerNet($f, $ali->capital_account_id))->toBe(0.0)
        ->and(partnerLedgerNet($f, $ali->drawing_account_id, 'debit'))->toBe(0.0);

    // Posting the day again puts them back once.
    partnerLedgerClose($f, [['partner_id' => $ali->id, 'amount' => 5000]]);
    expect((float) $ali->refresh()->total_invested)->toBe(5000.0);
});

test('withdrawing over the monthly drawing limit warns but still records, on the page and in the close', function () {
    $f = partnerLedgerFixture();
    $ali = partnerLedgerMake($f, 'Ali', 0, 'monthly', 10000);
    $url = "/{$f['company']->slug}/partners/{$ali->id}/withdraw";

    test()->post($url, ['amount' => 8000, 'transaction_date' => '2026-09-10', 'account_id' => $f['accounts']['1050']->id])
        ->assertSessionMissing('warning');
    test()->post($url, ['amount' => 5000, 'transaction_date' => '2026-09-11', 'account_id' => $f['accounts']['1050']->id])
        ->assertRedirect()
        ->assertSessionHas('warning', "Over Ali's monthly drawing limit by 3,000");

    expect(PartnerTransaction::where('partner_id', $ali->id)->where('transaction_type', 'withdrawal')->count())->toBe(2)
        ->and((float) $ali->refresh()->total_withdrawn)->toBe(13000.0)
        ->and($ali->withdrawnThisPeriod('2026-09-30'))->toBe(13000.0);

    // The close: 1,000 more on top of that month is over too; noted in its metadata, posted anyway.
    $posted = partnerLedgerClose($f, [], [['partner_id' => $ali->id, 'amount' => 1000]]);
    expect($posted['warnings'])->toBe(["Over Ali's monthly drawing limit by 4,000"])
        ->and(Transaction::find($posted['transaction_id'])->metadata['partner_limit_warnings'])->toBe(["Over Ali's monthly drawing limit by 4,000"])
        ->and((float) $ali->refresh()->total_withdrawn)->toBe(14000.0);
});

test('what a partner has withdrawn this period starts again next month', function () {
    $f = partnerLedgerFixture();
    $ali = partnerLedgerMake($f, 'Ali', 0, 'monthly', 10000);
    foreach ([['2026-09-05', 3000], ['2026-09-20', 4000], ['2026-08-30', 9000]] as [$date, $amount]) {
        PartnerTransaction::create(['company_id' => $f['company']->id, 'partner_id' => $ali->id, 'transaction_date' => $date, 'transaction_type' => 'withdrawal', 'amount' => $amount]);
    }

    expect($ali->withdrawnThisPeriod('2026-09-25'))->toBe(7000.0)
        ->and($ali->withdrawnThisPeriod('2026-10-01'))->toBe(0.0)
        ->and($ali->withdrawnThisPeriod('2026-08-31'))->toBe(9000.0);

    $ali->update(['drawing_limit_period' => 'yearly']);
    expect($ali->refresh()->withdrawnThisPeriod('2026-10-01'))->toBe(16000.0);
});

test('profit is shared by percentage, the rest stays unallocated, and sharing again replaces it', function () {
    $f = partnerLedgerFixture();
    $ali = partnerLedgerMake($f, 'Ali', 60);
    $bilal = partnerLedgerMake($f, 'Bilal', 30);
    $service = app(PartnerProfitShareService::class);
    partnerLedgerJournal($f, '2026-09-20', $f['accounts']['1050']->id, $f['accounts']['4100']->id, 100000);

    $preview = partnerLedgerRun($f, 'partner.share_profit', ['month' => '2026-09', 'dry_run' => true])['data'];
    expect($preview['net_profit'])->toBe(100000.0)->and($preview['unallocated'])->toBe(10000.0)
        ->and(Transaction::where('company_id', $f['company']->id)->where('transaction_type', 'partner_profit_share')->count())->toBe(0);

    $result = partnerLedgerRun($f, 'partner.share_profit', ['month' => '2026-09'])['data'];
    $first = Transaction::findOrFail($result['transaction_id']);
    $re = $service->retainedEarnings($f['company']->id);

    expect($first->transaction_date->toDateString())->toBe('2026-09-30')
        ->and($result['allocated'])->toBe(90000.0)->and($result['unallocated'])->toBe(10000.0)
        ->and(partnerLedgerNet($f, $ali->capital_account_id))->toBe(60000.0)
        ->and(partnerLedgerNet($f, $bilal->capital_account_id))->toBe(30000.0)
        ->and(partnerLedgerNet($f, $re->id, 'debit'))->toBe(90000.0)
        ->and((float) PartnerTransaction::where('partner_id', $ali->id)->where('transaction_type', 'profit_share')->sole()->amount)->toBe(60000.0)
        ->and($ali->refresh()->net_capital)->toBe(60000.0)
        ->and((float) $ali->total_invested)->toBe(0.0); // a profit share is not money put in

    // Same figures again: nothing is posted.
    $again = partnerLedgerRun($f, 'partner.share_profit', ['month' => '2026-09'])['data'];
    expect($again['unchanged'])->toBeTrue()
        ->and(Transaction::where('company_id', $f['company']->id)->where('transaction_type', 'partner_profit_share')->count())->toBe(1);

    // The month's profit grows: the first journal is reversed and the new one stands.
    partnerLedgerJournal($f, '2026-09-25', $f['accounts']['1050']->id, $f['accounts']['4100']->id, 50000);
    $replaced = partnerLedgerRun($f, 'partner.share_profit', ['month' => '2026-09'])['data'];

    expect($first->refresh()->reversed_by_id)->not->toBeNull()
        ->and($replaced['transaction_id'])->not->toBe($first->id)
        ->and(partnerLedgerNet($f, $ali->capital_account_id))->toBe(90000.0)
        ->and(partnerLedgerNet($f, $bilal->capital_account_id))->toBe(45000.0)
        ->and(PartnerTransaction::where('transaction_type', 'profit_share')->where('reference', '2026-09')->count())->toBe(2)
        ->and((float) PartnerTransaction::where('partner_id', $ali->id)->where('transaction_type', 'profit_share')->sole()->amount)->toBe(90000.0);
});

test('a loss is shared the other way round', function () {
    $f = partnerLedgerFixture();
    $ali = partnerLedgerMake($f, 'Ali', 60);
    $bilal = partnerLedgerMake($f, 'Bilal', 40);
    partnerLedgerJournal($f, '2026-09-18', $f['accounts']['6180']->id, $f['accounts']['1050']->id, 20000);

    $result = partnerLedgerRun($f, 'partner.share_profit', ['month' => '2026-09'])['data'];
    $re = app(PartnerProfitShareService::class)->retainedEarnings($f['company']->id);
    $entries = JournalEntry::where('transaction_id', $result['transaction_id'])->get();

    expect($result['net_profit'])->toBe(-20000.0)
        ->and((float) $entries->firstWhere('account_id', $ali->capital_account_id)->debit_amount)->toBe(12000.0)
        ->and((float) $entries->firstWhere('account_id', $bilal->capital_account_id)->debit_amount)->toBe(8000.0)
        ->and((float) $entries->firstWhere('account_id', $re->id)->credit_amount)->toBe(20000.0)
        ->and(partnerLedgerNet($f, $ali->capital_account_id))->toBe(-12000.0)
        ->and((float) PartnerTransaction::where('partner_id', $ali->id)->where('transaction_type', 'profit_share')->sole()->amount)->toBe(-12000.0);
});

test('a month whose daily closes are locked cannot have its share replaced', function () {
    $f = partnerLedgerFixture();
    $ali = partnerLedgerMake($f, 'Ali', 100);
    partnerLedgerClose($f);
    partnerLedgerRun($f, 'partner.share_profit', ['month' => '2026-09']);

    Transaction::where('company_id', $f['company']->id)->where('transaction_type', 'fuel_daily_close')
        ->update(['is_locked' => true, 'lock_reason' => 'month_end']);

    // Unchanged is still fine; a change is refused.
    expect(partnerLedgerRun($f, 'partner.share_profit', ['month' => '2026-09'])['data']['unchanged'])->toBeTrue();
    partnerLedgerJournal($f, '2026-09-28', $f['accounts']['1050']->id, $f['accounts']['4100']->id, 777);
    expect(fn () => partnerLedgerRun($f, 'partner.share_profit', ['month' => '2026-09']))->toThrow(ValidationException::class);
    expect(Transaction::where('company_id', $f['company']->id)->where('transaction_type', 'partner_profit_share')->whereNull('reversed_by_id')->whereNull('reversal_of_id')->count())->toBe(1);
});

test('net profit is the profit statement for a fuel company and the ledger P&L otherwise', function () {
    $fuel = partnerLedgerFixture();
    partnerLedgerClose($fuel);
    $expected = app(ProfitStatementService::class)->run($fuel['company']->id, '2026-09-01', '2026-09-30')['net_profit'];
    expect($expected)->not->toBe(0.0)
        ->and(app(PartnerProfitShareService::class)->netProfit($fuel['company']->id, '2026-09-01', '2026-09-30'))->toBe(round((float) $expected, 2));

    $plain = partnerLedgerFixture(false);
    partnerLedgerJournal($plain, '2026-09-20', $plain['accounts']['1050']->id, $plain['accounts']['4100']->id, 4321);
    expect(app(PartnerProfitShareService::class)->netProfit($plain['company']->id, '2026-09-01', '2026-09-30'))->toBe(4321.0);
});

test('the partner statement closes on capital less drawings, the same figure as the page', function () {
    $f = partnerLedgerFixture();
    $ali = partnerLedgerMake($f, 'Ali', 100);
    partnerLedgerRun($f, 'partner.invest', ['partner_id' => $ali->id, 'amount' => 50000, 'transaction_date' => '2026-09-02', 'account_id' => $f['accounts']['1050']->id]);
    partnerLedgerRun($f, 'partner.withdraw', ['partner_id' => $ali->id, 'amount' => 12000, 'transaction_date' => '2026-09-05', 'account_id' => $f['accounts']['1050']->id]);
    partnerLedgerJournal($f, '2026-09-20', $f['accounts']['1050']->id, $f['accounts']['4100']->id, 30000);
    partnerLedgerRun($f, 'partner.share_profit', ['month' => '2026-09']);
    partnerLedgerJournal($f, '2026-09-25', $f['accounts']['1050']->id, $f['accounts']['4100']->id, 10000);
    partnerLedgerRun($f, 'partner.share_profit', ['month' => '2026-09']); // replaced: a reversed pair in the range

    $ali->refresh();
    $statement = app(PartnerStatementService::class)->statement($ali, '2026-09-01', '2026-09-30');
    $moves = array_values(array_filter($statement['rows'], fn ($r) => ! in_array($r['type'], ['opening_balance', 'closing_balance'], true)));

    expect($statement['opening_balance'])->toBe(0.0)
        ->and($statement['closing_balance'])->toBe(78000.0)   // 50,000 - 12,000 + 40,000 profit
        ->and($statement['closing_balance'])->toBe($ali->net_capital)
        ->and($statement['closing_balance'])->toBe(round(partnerLedgerNet($f, $ali->capital_account_id) - partnerLedgerNet($f, $ali->drawing_account_id, 'debit'), 2))
        ->and(count($moves))->toBe(3) // invest, withdraw, the live share; the reversed pair is hidden
        ->and(collect($moves)->sum('money_in') - collect($moves)->sum('money_out'))->toBe(78000.0);

    // A later window opens on what came before.
    $october = app(PartnerStatementService::class)->statement($ali, '2026-10-01', '2026-10-31');
    expect($october['opening_balance'])->toBe(78000.0)->and($october['closing_balance'])->toBe(78000.0);

    test()->get("/{$f['company']->slug}/reports/statements?kind=partner&id={$ali->id}&from=2026-09-01&to=2026-09-30")->assertOk();
});
