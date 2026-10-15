<?php

use App\Models\Company;
use App\Models\User;
use App\Modules\Accounting\Models\Account;
use App\Modules\Accounting\Models\AccountingPeriod;
use App\Modules\Accounting\Models\FiscalYear;
use App\Modules\Accounting\Models\Transaction;
use App\Services\CommandBus;
use App\Services\CompanyContextService;
use Illuminate\Support\Facades\DB;

function journalPostFixture(): array
{
    $user = User::factory()->create();
    $company = Company::create(['name' => 'Journal Co', 'slug' => 'journal-co-'.str()->random(8), 'owner_id' => $user->id, 'base_currency' => 'PKR']);
    DB::statement("SELECT set_config('app.current_company_id', ?, false)", [$company->id]);
    $fy = FiscalYear::create(['company_id' => $company->id, 'name' => '2026', 'start_date' => '2026-01-01', 'end_date' => '2026-12-31', 'status' => 'open']);
    AccountingPeriod::create(['company_id' => $company->id, 'fiscal_year_id' => $fy->id, 'name' => 'September', 'period_number' => 9, 'start_date' => '2026-09-01', 'end_date' => '2026-09-30']);
    $cash = Account::create(['company_id' => $company->id, 'code' => '1050', 'name' => 'Cash', 'type' => 'asset', 'subtype' => 'cash', 'normal_balance' => 'debit', 'currency' => 'PKR']);
    $rent = Account::create(['company_id' => $company->id, 'code' => '6100', 'name' => 'Rent', 'type' => 'expense', 'subtype' => 'operating_expense', 'normal_balance' => 'debit', 'currency' => null]);

    return compact('user', 'company', 'cash', 'rent');
}

function journalDispatch(array $f, string $action, array $params)
{
    return app(CompanyContextService::class)->withContext($f['company'], fn () => app(CommandBus::class)->dispatch($action, $params, $f['user'], true));
}

function journalParams(array $f, array $extra = []): array
{
    return $extra + [
        'transaction_date' => '2026-09-15',
        'entries' => [
            ['account_id' => $f['rent']->id, 'type' => 'debit', 'amount' => 100],
            ['account_id' => $f['cash']->id, 'type' => 'credit', 'amount' => 100],
        ],
    ];
}

test('a journal created with post true is posted', function () {
    $f = journalPostFixture();
    $result = journalDispatch($f, 'journal.create', journalParams($f, ['post' => true]));
    expect($result['data']['status'])->toBe('posted');
});

test('a draft journal can be posted afterwards, and only once', function () {
    $f = journalPostFixture();
    $draft = journalDispatch($f, 'journal.create', journalParams($f));
    expect($draft['data']['status'])->toBe('draft');

    $this->actingAs($f['user']);
    $posted = journalDispatch($f, 'journal.post', ['id' => $draft['data']['id']]);
    expect($posted['data']['status'])->toBe('posted');
    $t = Transaction::findOrFail($draft['data']['id']);
    expect($t->posted_at)->not->toBeNull()->and($t->posted_by_user_id)->toBe($f['user']->id);

    expect(fn () => journalDispatch($f, 'journal.post', ['id' => $draft['data']['id']]))
        ->toThrow(\RuntimeException::class);
});

test('posting a journal needs the journal permission', function () {
    $f = journalPostFixture();
    $draft = journalDispatch($f, 'journal.create', journalParams($f));
    $stranger = User::factory()->create();
    expect(fn () => app(CompanyContextService::class)->withContext($f['company'], fn () => app(CommandBus::class)->dispatch('journal.post', ['id' => $draft['data']['id']], $stranger)))
        ->toThrow(\Illuminate\Auth\Access\AuthorizationException::class);
});
