<?php

use App\Models\Company;
use App\Models\User;
use App\Modules\Accounting\Models\Account;
use App\Modules\Accounting\Models\AccountingPeriod;
use App\Modules\Accounting\Models\FiscalYear;
use App\Modules\Accounting\Models\Transaction;
use App\Modules\Accounting\Services\GlPostingService;
use Illuminate\Support\Facades\DB;

/*
 * Shared by DailyCloseWorkflowTest.php and DailyCloseParkedOpeningsTest.php, per project
 * convention that a fixture reused across *Test.php files lives in its own *Fixtures.php file.
 */
function closeWorkflowFixture(): array
{
    $user = User::factory()->create();
    $company = Company::create(['name' => 'Close workflow', 'slug' => 'close-'.str()->random(12), 'owner_id' => $user->id, 'base_currency' => 'PKR']);
    DB::statement("SELECT set_config('app.current_company_id', ?, false)", [$company->id]);
    test()->actingAs($user);
    $fy = FiscalYear::create(['company_id' => $company->id, 'name' => '2026', 'start_date' => '2026-01-01', 'end_date' => '2026-12-31', 'status' => 'open']);
    AccountingPeriod::create(['company_id' => $company->id, 'fiscal_year_id' => $fy->id, 'name' => 'September', 'period_number' => 9, 'start_date' => '2026-09-01', 'end_date' => '2026-09-30']);
    $accounts = [];
    foreach ([['1050','asset','cash','debit'], ['1200','asset','inventory','debit'], ['4100','revenue','other_income','credit'], ['5100','cogs','cost_of_goods_sold','debit'], ['6180','expense','operating_expense','debit']] as [$code,$type,$subtype,$normal]) {
        $accounts[$code] = Account::create(['company_id' => $company->id, 'code' => $code, 'name' => $code, 'type' => $type, 'subtype' => $subtype, 'normal_balance' => $normal, 'currency' => $code === '1050' ? 'PKR' : null, 'is_active' => true]);
    }
    // This fixture has no configured nozzles at all, so every post here is a
    // genuine zero-sales-day close; confirm it explicitly so the HTTP/CommandBus
    // validation path (which now refuses an unconfirmed empty post) accepts it.
    $payload = ['date' => '2026-09-15', 'opening_cash' => 420000, 'closing_cash' => 410000, 'nozzle_readings' => [],
        'zero_sales_confirmed' => true, 'zero_sales_reason' => 'No nozzles configured in this test fixture'];
    return compact('user', 'company', 'accounts', 'payload');
}

function workflowExpense(array $f, string $date = '2026-09-15', float $amount = 10000): Transaction
{
    return app(GlPostingService::class)->postBalancedTransaction([
        'company_id' => $f['company']->id, 'transaction_type' => 'expense', 'date' => $date, 'currency' => 'PKR',
    ], [
        ['account_id' => $f['accounts']['6180']->id, 'type' => 'debit', 'amount' => $amount],
        ['account_id' => $f['accounts']['1050']->id, 'type' => 'credit', 'amount' => $amount],
    ]);
}

function enableCloseHttp(array $f): void
{
    DB::select("SELECT set_config('app.current_user_id', ?, false)", [$f['user']->id]);
    DB::select("SELECT set_config('app.is_super_admin', 'true', false)");
    app(\App\Services\CompanyRbacBootstrapper::class)->bootstrap($f['company']);
    DB::table('auth.company_user')->insert(['company_id' => $f['company']->id, 'user_id' => $f['user']->id, 'role' => 'owner', 'joined_at' => now(), 'is_active' => true, 'created_at' => now(), 'updated_at' => now()]);
    app(\App\Services\CompanyContextService::class)->withContext($f['company'], fn () => app(\App\Services\CompanyContextService::class)->assignRole($f['user'], 'owner'));
    $f['company']->enableModule('fuel_station');
    DB::select("SELECT set_config('app.is_super_admin', 'false', false)");
    test()->actingAs($f['user']);
}
