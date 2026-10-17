<?php

use App\Models\Company;
use App\Models\User;
use App\Modules\Accounting\Models\Account;
use App\Modules\Accounting\Models\AccountingPeriod;
use App\Modules\Accounting\Models\FiscalYear;
use App\Modules\Accounting\Models\Invoice;
use App\Modules\Accounting\Models\PostingTemplate;
use App\Modules\Accounting\Models\PostingTemplateLine;
use App\Services\CommandBus;
use App\Services\CompanyContextService;
use App\Services\CompanyRbacBootstrapper;
use Illuminate\Support\Facades\DB;

require_once __DIR__.'/CashSaleFixtures.php';

test('sale.create posts the same journal as the sales page for the same input', function () {
    $f = cashSaleFixture();

    $this->actingAs($f['owner'])->post("/{$f['company']->slug}/sales", [
        'sale_date' => $f['today'],
        'deposit_account_id' => $f['cash']->id,
        'line_items' => [['description' => 'Cash sale', 'amount' => 1200]],
    ])->assertSessionHasNoErrors()->assertRedirect();

    $viaPage = cashSaleJournal($f['company']);
    $pageIds = DB::table('acct.transactions')->where('company_id', $f['company']->id)->pluck('id')->all();
    expect($viaPage)->not->toBeEmpty();

    $this->actingAs($f['owner']);
    $result = app(CompanyContextService::class)->withContext($f['company'], fn () => app(CommandBus::class)->dispatch('sale.create', [
        'amount' => 1200, 'deposit_account_id' => $f['cash']->id,
    ], $f['owner']));

    expect($result['data']['invoice_id'])->not->toBeNull();
    expect(cashSaleJournal($f['company'], $pageIds))->toBe($viaPage);
    expect(Invoice::where('company_id', $f['company']->id)->count())->toBe(2);
    expect((float) Invoice::findOrFail($result['data']['invoice_id'])->balance)->toBe(0.0);
});

test('sale.create defaults the date to the company date and the deposit account to the sales page default', function () {
    $f = cashSaleFixture();
    $f['company']->bank_account_id = $f['bank']->id;
    $f['company']->save();

    $this->actingAs($f['owner']);
    $result = app(CompanyContextService::class)->withContext($f['company'], fn () => app(CommandBus::class)->dispatch('sale.create', ['amount' => 500], $f['owner']));

    $invoice = Invoice::findOrFail($result['data']['invoice_id']);
    expect($invoice->invoice_date->toDateString())->toBe($f['today']);
    expect(cashSaleJournal($f['company']))->toContain('1060|500|0');
});

test('sale.create needs both invoice and payment permission', function () {
    $f = cashSaleFixture();
    $this->actingAs($f['limited']);

    expect(fn () => app(CompanyContextService::class)->withContext($f['company'], fn () => app(CommandBus::class)->dispatch('sale.create', [
        'amount' => 100, 'deposit_account_id' => $f['cash']->id,
    ], $f['limited'])))->toThrow(\Illuminate\Auth\Access\AuthorizationException::class);

    expect(DB::table('acct.transactions')->where('company_id', $f['company']->id)->count())->toBe(0);
});

test('sale.create refuses a deposit account that is not cash or bank', function () {
    $f = cashSaleFixture();
    $this->actingAs($f['owner']);

    expect(fn () => app(CompanyContextService::class)->withContext($f['company'], fn () => app(CommandBus::class)->dispatch('sale.create', [
        'amount' => 100, 'deposit_account_id' => $f['revenue']->id,
    ], $f['owner'])))->toThrow(\Illuminate\Validation\ValidationException::class);
});
