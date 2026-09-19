<?php

use App\Models\Company;
use App\Models\User;
use App\Modules\Accounting\Models\Account;
use App\Modules\Accounting\Models\AccountingPeriod;
use App\Modules\Accounting\Models\FiscalYear;
use App\Modules\Accounting\Models\Transaction;
use App\Modules\FuelStation\Models\NozzleReading;
use App\Modules\FuelStation\Models\Nozzle;
use App\Modules\FuelStation\Models\Pump;
use App\Modules\FuelStation\Models\TankReading;
use App\Modules\FuelStation\Services\DailyCloseReconciliationService;
use App\Modules\FuelStation\Services\DailyCloseService;
use App\Modules\FuelStation\Services\StockVarianceReportService;
use App\Modules\FuelStation\Services\ProductProfitabilityReportService;
use App\Modules\Inventory\Models\Item;
use App\Modules\Inventory\Models\StockMovement;
use App\Modules\Inventory\Models\Warehouse;
use App\Services\CommandBus;
use App\Services\CompanyContextService;
use Illuminate\Support\Facades\DB;

function correctionFixture(): array
{
    $user = User::factory()->create();
    $company = Company::create(['name' => 'Correction test', 'slug' => 'correction-'.str()->random(12), 'owner_id' => $user->id, 'base_currency' => 'PKR']);
    DB::statement("SELECT set_config('app.current_company_id', ?, false)", [$company->id]);
    test()->actingAs($user);

    $fy = FiscalYear::create(['company_id' => $company->id, 'name' => '2026', 'start_date' => '2026-01-01', 'end_date' => '2026-12-31', 'status' => 'open']);
    AccountingPeriod::create(['company_id' => $company->id, 'fiscal_year_id' => $fy->id, 'name' => 'September', 'period_number' => 9, 'start_date' => '2026-09-01', 'end_date' => '2026-09-30']);
    $accounts = [];
    foreach ([['1050', 'asset', 'cash', 'debit'], ['1200', 'asset', 'inventory', 'debit'], ['4100', 'revenue', 'other_income', 'credit'], ['5100', 'cogs', 'cost_of_goods_sold', 'debit'], ['6180', 'expense', 'operating_expense', 'debit'], ['5900', 'expense', 'operating_expense', 'debit'], ['4900', 'revenue', 'other_income', 'credit']] as [$code, $type, $subtype, $normal]) {
        $accounts[$code] = Account::create(['company_id' => $company->id, 'code' => $code, 'name' => $code, 'type' => $type, 'subtype' => $subtype, 'normal_balance' => $normal, 'currency' => $code === '1050' ? 'PKR' : null, 'is_active' => true]);
    }

    $item = Item::create([
        'company_id' => $company->id, 'sku' => 'DIESEL-'.str()->random(6), 'name' => 'Diesel',
        'item_type' => 'product', 'unit_of_measure' => 'liter', 'currency' => 'PKR', 'avg_cost' => 250,
    ]);
    $tank = Warehouse::create(['company_id' => $company->id, 'code' => 'TANK-'.str()->random(6), 'name' => 'Tank 1', 'linked_item_id' => $item->id]);
    $pump = Pump::create(['company_id' => $company->id, 'name' => 'Pump 1', 'tank_id' => $tank->id]);
    $nozzle = Nozzle::create(['company_id' => $company->id, 'pump_id' => $pump->id, 'tank_id' => $tank->id, 'item_id' => $item->id, 'code' => 'N1', 'label' => 'Nozzle 1']);

    StockMovement::create([
        'company_id' => $company->id, 'warehouse_id' => $tank->id, 'item_id' => $item->id,
        'movement_date' => '2026-08-31', 'movement_type' => 'opening', 'quantity' => 10000,
        'unit_cost' => 250, 'total_cost' => 2500000, 'created_by_user_id' => $user->id,
    ]);

    $payload = [
        'date' => '2026-09-15', 'opening_cash' => 100000, 'closing_cash' => 130000,
        'nozzle_readings' => [[
            'nozzle_id' => $nozzle->id, 'item_id' => $item->id,
            'opening_electronic' => 0, 'closing_electronic' => 100,
            'opening_manual' => 0, 'closing_manual' => 100,
            'liters_sold' => 100, 'sale_rate' => 300,
        ]],
        'tank_readings' => [[
            'tank_id' => $tank->id, 'stick_reading' => 100, 'liters' => 9900,
        ]],
    ];

    return compact('user', 'company', 'accounts', 'item', 'tank', 'pump', 'nozzle', 'payload');
}

function dispatchCorrection(array $f, array $params): array
{
    return app(CompanyContextService::class)->withContext($f['company'], fn () => app(CommandBus::class)->dispatch('fuel.daily_close.correct_reading', $params, $f['user'], true));
}

test('a nozzle correction adjusts reconciled sales and tank variance but leaves the posted snapshot byte-identical', function () {
    $f = correctionFixture();
    $result = app(DailyCloseService::class)->processDailyClose($f['company']->id, $f['payload'], $f['user']);
    $close = Transaction::findOrFail($result['transaction_id']);
    $snapshotBefore = $close->metadata['posting_snapshot'];

    $nozzleReading = NozzleReading::where('company_id', $f['company']->id)->where('daily_close_transaction_id', $close->id)->firstOrFail();
    expect((float) $nozzleReading->liters_dispensed)->toBe(100.0);

    dispatchCorrection($f, [
        'close_id' => $close->id,
        'reading_type' => 'nozzle',
        'reading_id' => $nozzleReading->id,
        'corrected_value' => 120,
        'reason' => 'Meter misread at close, verified against attendant log',
    ]);

    // The original reading row is untouched.
    expect((float) $nozzleReading->fresh()->liters_dispensed)->toBe(100.0);

    // The posted snapshot section is byte-identical.
    expect($close->fresh()->metadata['posting_snapshot'])->toBe($snapshotBefore);

    $view = app(DailyCloseReconciliationService::class)->view($close->fresh());
    expect($view['corrections'])->toHaveCount(1);
    expect($view['corrections'][0]['original_value'])->toBe(100.0);
    expect($view['corrections'][0]['corrected_value'])->toBe(120.0);
    expect($view['has_post_close_activity'])->toBeTrue();

    // 20 extra liters at 300/liter = 6000 extra revenue, and 20 extra liters
    // drawn from the tank drops expected_liters by 20, widening the tank
    // variance (physical dip was recorded as if only 100L had been sold).
    expect($view['current']['total_revenue'])->toBe((float) ($snapshotBefore['totals']['total_revenue'] + 6000));
    expect($view['current']['expected_closing'])->toBe((float) ($snapshotBefore['totals']['expected_closing'] + 6000));

    $tank = collect($view['current']['tanks'])->firstWhere('tank_id', $f['tank']->id);
    $originalTank = collect($snapshotBefore['tanks'])->firstWhere('tank_id', $f['tank']->id);
    expect($tank['expected_liters'])->toBe(round($originalTank['expected_liters'] - 20, 3));
    expect($tank['variance_liters'])->toBe(round($tank['physical_liters'] - $tank['expected_liters'], 3));
});

test('a tank correction adjusts physical liters and variance but leaves the posted snapshot byte-identical', function () {
    $f = correctionFixture();
    $result = app(DailyCloseService::class)->processDailyClose($f['company']->id, $f['payload'], $f['user']);
    $close = Transaction::findOrFail($result['transaction_id']);
    $snapshotBefore = $close->metadata['posting_snapshot'];

    $tankReading = TankReading::where('company_id', $f['company']->id)->whereDate('reading_date', '2026-09-15')->firstOrFail();
    expect((float) $tankReading->dip_measurement_liters)->toBe(9900.0);

    dispatchCorrection($f, [
        'close_id' => $close->id,
        'reading_type' => 'tank',
        'reading_id' => $tankReading->id,
        'corrected_value' => 9950,
        'reason' => 'Dip stick misread, re-measured by supervisor',
    ]);

    expect((float) $tankReading->fresh()->dip_measurement_liters)->toBe(9900.0);
    expect($close->fresh()->metadata['posting_snapshot'])->toBe($snapshotBefore);

    $view = app(DailyCloseReconciliationService::class)->view($close->fresh());
    $tank = collect($view['current']['tanks'])->firstWhere('tank_id', $f['tank']->id);
    $originalTank = collect($snapshotBefore['tanks'])->firstWhere('tank_id', $f['tank']->id);
    expect($tank['physical_liters'])->toBe(round($originalTank['physical_liters'] + 50, 3));
    expect($tank['variance_liters'])->toBe(round($tank['physical_liters'] - $tank['expected_liters'], 3));
});

test('stock variance report includes reconciled tank corrections at frozen cost', function () {
    $f = correctionFixture();
    $result = app(DailyCloseService::class)->processDailyClose($f['company']->id, $f['payload'], $f['user']);
    $close = Transaction::findOrFail($result['transaction_id']);
    $tankReading = TankReading::where('company_id', $f['company']->id)->whereDate('reading_date', '2026-09-15')->firstOrFail();

    dispatchCorrection($f, [
        'close_id' => $close->id,
        'reading_type' => 'tank',
        'reading_id' => $tankReading->id,
        'corrected_value' => 9950,
        'reason' => 'Dip stick misread, re-measured by supervisor',
    ]);

    $report = app(StockVarianceReportService::class)->run(
        $f['company']->id,
        '2026-09-15',
        '2026-09-15',
    );

    expect($report['totals']['physical_gain_liters'])->toBe(50.0);
    expect($report['totals']['physical_gain_value'])->toBe(12500.0);
    expect($report['physicalRows'])->toHaveCount(1);
    expect($report['physicalRows'][0]['dip_liters'])->toBe(9950.0);
    expect($report['physicalRows'][0]['expected_liters'])->toBe(9900.0);
    expect($report['physicalRows'][0]['variance_type'])->toBe(TankReading::VARIANCE_GAIN);
    expect($report['physicalRows'][0]['unit_cost'])->toBe(250.0);

    $gainOnlyReport = app(StockVarianceReportService::class)->run(
        $f['company']->id,
        '2026-09-15',
        '2026-09-15',
        'all',
        'all',
        TankReading::VARIANCE_GAIN,
    );

    expect($gainOnlyReport['physicalRows'])->toHaveCount(1);
    expect($gainOnlyReport['totals']['physical_gain_liters'])->toBe(50.0);

    $profitability = app(ProductProfitabilityReportService::class)->run(
        $f['company']->id,
        '2026-09-15',
        '2026-09-15',
    );

    expect($profitability['totals']['stock_gain_quantity'])->toBe(50.0);
    expect($profitability['totals']['stock_gain_value'])->toBe(12500.0);
});

test('the correction table is append-only: update and delete are rejected', function () {
    $f = correctionFixture();
    $result = app(DailyCloseService::class)->processDailyClose($f['company']->id, $f['payload'], $f['user']);
    $close = Transaction::findOrFail($result['transaction_id']);
    $tankReading = TankReading::where('company_id', $f['company']->id)->whereDate('reading_date', '2026-09-15')->firstOrFail();

    $correction = dispatchCorrection($f, [
        'close_id' => $close->id, 'reading_type' => 'tank', 'reading_id' => $tankReading->id,
        'corrected_value' => 9950, 'reason' => 'Initial correction',
    ]);

    expect(fn () => DB::transaction(fn () => DB::table('fuel.daily_close_reading_corrections')->where('id', $correction['id'])->update(['corrected_value' => 10000])))
        ->toThrow(\Illuminate\Database\QueryException::class, 'append-only');
    expect(fn () => DB::transaction(fn () => DB::table('fuel.daily_close_reading_corrections')->where('id', $correction['id'])->delete()))
        ->toThrow(\Illuminate\Database\QueryException::class, 'append-only');
});

test('a reading or close belonging to another company is rejected and writes nothing', function () {
    $f = correctionFixture();
    $result = app(DailyCloseService::class)->processDailyClose($f['company']->id, $f['payload'], $f['user']);
    $close = Transaction::findOrFail($result['transaction_id']);
    $tankReading = TankReading::where('company_id', $f['company']->id)->whereDate('reading_date', '2026-09-15')->firstOrFail();

    $other = correctionFixture();

    expect(fn () => dispatchCorrection($other, [
        'close_id' => $close->id, 'reading_type' => 'tank', 'reading_id' => $tankReading->id,
        'corrected_value' => 9950, 'reason' => 'Cross-company attempt',
    ]))->toThrow(\Illuminate\Validation\ValidationException::class);

    expect(DB::table('fuel.daily_close_reading_corrections')->where('close_transaction_id', $close->id)->count())->toBe(0);
});

test('a correction is rejected on an unposted or legacy close', function () {
    $f = correctionFixture();
    $legacy = Transaction::create([
        'company_id' => $f['company']->id, 'transaction_number' => 'FDC-LEGACY-1', 'transaction_type' => 'fuel_daily_close',
        'transaction_date' => '2026-09-10', 'posting_date' => '2026-09-10',
        'fiscal_year_id' => FiscalYear::where('company_id', $f['company']->id)->value('id'),
        'period_id' => AccountingPeriod::where('company_id', $f['company']->id)->value('id'),
        'currency' => 'PKR', 'base_currency' => 'PKR', 'status' => 'posted', 'metadata' => [],
    ]);

    $tankReading = TankReading::create([
        'company_id' => $f['company']->id, 'tank_id' => $f['tank']->id, 'item_id' => $f['item']->id,
        'reading_date' => '2026-09-10', 'reading_type' => 'closing', 'stick_reading' => 100,
        'dip_measurement_liters' => 9900, 'system_calculated_liters' => 9900, 'variance_liters' => 0,
        'variance_type' => 'none', 'status' => 'posted', 'recorded_by_user_id' => $f['user']->id,
    ]);

    expect(fn () => dispatchCorrection($f, [
        'close_id' => $legacy->id, 'reading_type' => 'tank', 'reading_id' => $tankReading->id,
        'corrected_value' => 9950, 'reason' => 'Legacy close attempt',
    ]))->toThrow(\Illuminate\Validation\ValidationException::class);

    expect(DB::table('fuel.daily_close_reading_corrections')->where('close_transaction_id', $legacy->id)->count())->toBe(0);
});

function enableCorrectionHttp(array $f): void
{
    DB::select("SELECT set_config('app.current_user_id', ?, false)", [$f['user']->id]);
    DB::select("SELECT set_config('app.is_super_admin', 'true', false)");
    app(\App\Services\CompanyRbacBootstrapper::class)->bootstrap($f['company']);
    DB::table('auth.company_user')->insert(['company_id' => $f['company']->id, 'user_id' => $f['user']->id, 'role' => 'owner', 'joined_at' => now(), 'is_active' => true, 'created_at' => now(), 'updated_at' => now()]);
    app(CompanyContextService::class)->withContext($f['company'], fn () => app(CompanyContextService::class)->assignRole($f['user'], 'owner'));
    $f['company']->enableModule('fuel_station');
    DB::select("SELECT set_config('app.is_super_admin', 'false', false)");
    test()->actingAs($f['user']);
}

test('the correction route requires the correct permission', function () {
    $f = correctionFixture();
    enableCorrectionHttp($f);
    $result = app(DailyCloseService::class)->processDailyClose($f['company']->id, $f['payload'], $f['user']);
    $close = Transaction::findOrFail($result['transaction_id']);
    $tankReading = TankReading::where('company_id', $f['company']->id)->whereDate('reading_date', '2026-09-15')->firstOrFail();

    $stranger = User::factory()->create();
    DB::table('auth.company_user')->insert(['company_id' => $f['company']->id, 'user_id' => $stranger->id, 'role' => 'operations', 'joined_at' => now(), 'is_active' => true, 'created_at' => now(), 'updated_at' => now()]);
    app(CompanyContextService::class)->withContext($f['company'], fn () => app(CompanyContextService::class)->assignRole($stranger, 'operations'));
    test()->actingAs($stranger);

    $response = test()->post("/{$f['company']->slug}/fuel/daily-close/{$close->id}/corrections", [
        'reading_type' => 'tank', 'reading_id' => $tankReading->id,
        'corrected_value' => 9950, 'reason' => 'No permission',
    ]);
    $response->assertForbidden();
    expect(DB::table('fuel.daily_close_reading_corrections')->where('close_transaction_id', $close->id)->count())->toBe(0);

    test()->actingAs($f['user']);
    $ok = test()->post("/{$f['company']->slug}/fuel/daily-close/{$close->id}/corrections", [
        'reading_type' => 'tank', 'reading_id' => $tankReading->id,
        'corrected_value' => 9950, 'reason' => 'Owner has permission',
    ]);
    $ok->assertSessionHasNoErrors()->assertSessionHas('success');
    expect(DB::table('fuel.daily_close_reading_corrections')->where('close_transaction_id', $close->id)->count())->toBe(1);
});

test('repeated corrections use previous values and frozen costs with canonical journals and stock', function () {
    $f = correctionFixture();
    $result = app(DailyCloseService::class)->processDailyClose($f['company']->id, $f['payload'], $f['user']);
    $close = Transaction::findOrFail($result['transaction_id']);
    $snapshot = $close->metadata;
    $nozzle = NozzleReading::where('daily_close_transaction_id', $close->id)->firstOrFail();
    $tank = TankReading::where('company_id', $f['company']->id)->whereDate('reading_date', '2026-09-15')->firstOrFail();
    $f['item']->update(['avg_cost' => 999]);
    foreach ([90, 95] as $value) {
        dispatchCorrection($f, ['close_id' => $close->id, 'reading_type' => 'nozzle', 'reading_id' => $nozzle->id, 'corrected_value' => $value, 'reason' => 'Register correction']);
    }
    foreach ([9950, 9925] as $value) {
        dispatchCorrection($f, ['close_id' => $close->id, 'reading_type' => 'tank', 'reading_id' => $tank->id, 'corrected_value' => $value, 'reason' => 'Dip correction']);
    }
    $view = app(DailyCloseReconciliationService::class)->view($close->fresh());
    expect($view['current']['total_revenue'])->toBe(28500.0);
    expect($view['current']['expected_closing'])->toBe(128500.0);
    expect((float) $view['current']['closing_cash'])->toBe(130000.0);
    expect($view['current']['tanks'][0]['physical_liters'])->toBe(9925.0);
    expect($view['current']['tanks'][0]['expected_liters'])->toBe(9905.0);
    expect($close->fresh()->metadata)->toBe($snapshot);
    $corrections = DB::table('fuel.daily_close_reading_corrections')->where('close_transaction_id', $close->id)->get();
    expect($corrections)->toHaveCount(4);
    expect((float) $corrections->where('reading_id', $nozzle->id)->firstWhere('revision', 2)->original_value)->toBe(90.0);
    expect((float) StockMovement::where('company_id', $f['company']->id)->sum('quantity'))->toBe(9925.0);
    $transactions = Transaction::where('company_id', $f['company']->id)->where('transaction_type', 'fuel_reading_correction')->with('journalEntries')->get();
    expect($transactions)->toHaveCount(4);
    $effects = [];
    foreach ($transactions as $transaction) {
        expect((float) $transaction->total_debit)->toBe((float) $transaction->total_credit);
        foreach ($transaction->journalEntries as $line) {
            $effects[$line->account_id] = ($effects[$line->account_id] ?? 0) + $line->debit_amount - $line->credit_amount;
        }
    }
    expect($effects[$f['accounts']['5100']->id])->toBe(-1250.0); // 5 litres * frozen Rs250
    expect($effects[$f['accounts']['1200']->id])->toBe(6250.0); // physical stock +25 litres
    expect($effects[$f['accounts']['1050']->id] ?? 0)->toBe(0); // counted cash never changes
    $journal = $transactions->first();
    expect(fn () => DB::transaction(fn () => $journal->update(['reversed_by_id' => $close->id])))->toThrow(\Illuminate\Database\QueryException::class);
});

test('a nozzle correction affects only its frozen supplying tank and leaves downstream closes unchanged', function () {
    $f = correctionFixture();
    $other = Warehouse::create(['company_id' => $f['company']->id, 'code' => 'T2', 'name' => 'Second diesel tank', 'linked_item_id' => $f['item']->id]);
    StockMovement::create(['company_id' => $f['company']->id, 'warehouse_id' => $other->id, 'item_id' => $f['item']->id, 'movement_date' => '2026-08-31', 'movement_type' => 'opening', 'quantity' => 2000, 'unit_cost' => 250, 'total_cost' => 500000, 'created_by_user_id' => $f['user']->id]);
    $f['payload']['tank_readings'][] = ['tank_id' => $other->id, 'liters' => 2000];
    $posted = app(DailyCloseService::class)->processDailyClose($f['company']->id, $f['payload'], $f['user']);
    $close = Transaction::findOrFail($posted['transaction_id']);
    $unchangedTank = collect($close->metadata['posting_snapshot']['tanks'])->firstWhere('tank_id', $other->id);
    expect($unchangedTank['expected_liters'])->toBe(2000);
    $nextPayload = $f['payload']; $nextPayload['date'] = '2026-09-16';
    $nextPayload['opening_cash'] = 130000; $nextPayload['closing_cash'] = 160000;
    $nextPayload['nozzle_readings'][0]['opening_electronic'] = 100; $nextPayload['nozzle_readings'][0]['closing_electronic'] = 200;
    $nextPayload['tank_readings'][0]['liters'] = 9800;
    $next = app(DailyCloseService::class)->processDailyClose($f['company']->id, $nextPayload, $f['user']);
    $downstream = Transaction::findOrFail($next['transaction_id']); $before = $downstream->metadata;
    $reading = NozzleReading::where('daily_close_transaction_id', $close->id)->firstOrFail();
    $f['nozzle']->update(['tank_id' => $other->id]); // reassignment today must not rewrite historical attribution
    dispatchCorrection($f, ['close_id' => $close->id, 'reading_type' => 'nozzle', 'reading_id' => $reading->id, 'corrected_value' => 90, 'reason' => 'Meter typo', 'expected_revision' => 0]);
    expect(fn () => dispatchCorrection($f, ['close_id' => $close->id, 'reading_type' => 'nozzle', 'reading_id' => $reading->id, 'corrected_value' => 95, 'reason' => 'Stale screen', 'expected_revision' => 0]))->toThrow(\Illuminate\Validation\ValidationException::class);
    $view = app(DailyCloseReconciliationService::class)->view($close->fresh());
    expect(collect($view['current']['tanks'])->firstWhere('tank_id', $other->id))->toBe($unchangedTank);
    expect($downstream->fresh()->metadata)->toBe($before);
});

test('audit trigger works as a restricted role without leaking tenant context or bypassing source RLS', function () {
    requiresPrivilegedDatabaseRole('Creating a throwaway database role needs a privileged connection.');

    $f = correctionFixture();
    $posted = app(DailyCloseService::class)->processDailyClose($f['company']->id, $f['payload'], $f['user']);
    $role = 'close_test_'.strtolower(str()->random(10));
    DB::statement("CREATE ROLE {$role} NOLOGIN NOSUPERUSER NOBYPASSRLS");
    DB::statement("GRANT USAGE ON SCHEMA acct, fuel TO {$role}");
    DB::statement("GRANT SELECT ON acct.transactions TO {$role}");
    DB::statement("GRANT SELECT, INSERT ON fuel.daily_close_activity TO {$role}");
    DB::statement('CREATE TEMP TABLE payments (id uuid, company_id uuid, payment_date date, amount numeric)');
    DB::statement('CREATE TRIGGER capture BEFORE INSERT ON pg_temp.payments FOR EACH ROW EXECUTE FUNCTION fuel.capture_post_close_activity()');
    DB::statement("GRANT SELECT, INSERT ON pg_temp.payments TO {$role}");
    DB::statement("SELECT set_config('app.is_super_admin', 'false', true)");
    DB::statement("SELECT set_config('app.current_company_id', '', true)");
    DB::statement("SET LOCAL ROLE {$role}");
    try {
        expect(DB::selectOne('SELECT rolsuper, rolbypassrls FROM pg_roles WHERE rolname=current_user')->rolsuper)->toBeFalse();
        DB::table('pg_temp.payments')->insert(['id' => (string) str()->uuid(), 'company_id' => $f['company']->id, 'payment_date' => '2026-09-15', 'amount' => 10]);
        expect(DB::selectOne("SELECT current_setting('app.current_company_id') AS context")->context)->toBe('');
        expect(DB::table('fuel.daily_close_activity')->count())->toBe(0);
        DB::statement("SELECT set_config('app.current_company_id', ?, true)", [$f['company']->id]);
        expect(DB::table('fuel.daily_close_activity')->where('close_transaction_id', $posted['transaction_id'])->count())->toBeGreaterThan(0);
        DB::statement("SELECT set_config('app.current_company_id', ?, true)", [(string) str()->uuid()]);
        expect(DB::table('fuel.daily_close_activity')->count())->toBe(0);
        expect(fn () => DB::transaction(fn () => DB::table('pg_temp.payments')->insert(['id' => (string) str()->uuid(), 'company_id' => $f['company']->id, 'payment_date' => '2026-09-15', 'amount' => 20])))->toThrow(\Illuminate\Database\QueryException::class);
    } finally { DB::statement('RESET ROLE'); }
    DB::statement('ALTER TABLE pg_temp.payments ENABLE ROW LEVEL SECURITY');
    DB::statement("CREATE POLICY source_tenant ON pg_temp.payments WITH CHECK (company_id = nullif(current_setting('app.current_company_id',true),'')::uuid)");
    DB::statement("SELECT set_config('app.current_company_id', '', true)");
    DB::statement("SET LOCAL ROLE {$role}");
    try {
        expect(fn () => DB::transaction(fn () => DB::table('pg_temp.payments')->insert(['id' => (string) str()->uuid(), 'company_id' => $f['company']->id, 'payment_date' => '2026-09-15', 'amount' => 30])))->toThrow(\Illuminate\Database\QueryException::class);
    } finally { DB::statement('RESET ROLE'); }
});

test('meter correction crossing a rate boundary uses posting-time prices after rate records change', function () {
    $f = correctionFixture();
    $old = \App\Modules\FuelStation\Models\RateChange::create(['company_id' => $f['company']->id, 'item_id' => $f['item']->id, 'effective_date' => '2026-09-14', 'purchase_rate' => 250, 'sale_rate' => 280, 'created_by_user_id' => $f['user']->id]);
    $new = \App\Modules\FuelStation\Models\RateChange::create(['company_id' => $f['company']->id, 'item_id' => $f['item']->id, 'effective_date' => '2026-09-15', 'purchase_rate' => 250, 'sale_rate' => 300, 'snapshot_nozzle_readings' => [['nozzle_id' => $f['nozzle']->id, 'electronic_reading' => 40]], 'created_by_user_id' => $f['user']->id]);
    $posted = app(DailyCloseService::class)->processDailyClose($f['company']->id, $f['payload'], $f['user']);
    $close = Transaction::findOrFail($posted['transaction_id']);
    expect((float) $close->metadata['posting_snapshot']['totals']['total_revenue'])->toBe(29200.0);
    $old->update(['sale_rate' => 1]); $new->update(['sale_rate' => 999]);
    $reading = NozzleReading::where('daily_close_transaction_id', $close->id)->firstOrFail();
    dispatchCorrection($f, ['close_id' => $close->id, 'reading_type' => 'nozzle', 'reading_id' => $reading->id, 'corrected_value' => 20, 'reason' => 'Wrong closing meter copied from register']);
    $view = app(DailyCloseReconciliationService::class)->view($close->fresh());
    expect($view['current']['total_revenue'])->toBe(5600.0);
    expect((float) $close->fresh()->metadata['posting_snapshot']['totals']['total_revenue'])->toBe(29200.0);
});
