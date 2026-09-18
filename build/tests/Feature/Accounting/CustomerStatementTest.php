<?php

use App\Models\Company;
use App\Models\User;
use App\Modules\Accounting\Models\Account;
use App\Modules\Accounting\Models\AccountingPeriod;
use App\Modules\Accounting\Models\Customer;
use App\Modules\Accounting\Models\FiscalYear;
use App\Modules\Accounting\Models\Invoice;
use App\Modules\Accounting\Models\PostingTemplate;
use App\Modules\Accounting\Models\PostingTemplateLine;
use App\Modules\Accounting\Services\CustomerStatementService;
use App\Services\CommandBus;
use App\Services\CompanyContextService;
use Illuminate\Support\Facades\DB;

function statementFixture(): array
{
    $user = User::factory()->create();
    $company = Company::create(['name' => 'Statement Co', 'slug' => 'statement-co-'.str()->random(8), 'owner_id' => $user->id, 'base_currency' => 'PKR']);
    DB::statement("SELECT set_config('app.current_company_id', ?, false)", [$company->id]);
    $fy = FiscalYear::create(['company_id' => $company->id, 'name' => '2026', 'start_date' => '2026-01-01', 'end_date' => '2026-12-31', 'status' => 'open']);
    AccountingPeriod::create(['company_id' => $company->id, 'fiscal_year_id' => $fy->id, 'name' => 'September', 'period_number' => 9, 'start_date' => '2026-09-01', 'end_date' => '2026-09-30']);
    $ar = Account::create(['company_id' => $company->id, 'code' => '1100', 'name' => 'AR', 'type' => 'asset', 'subtype' => 'accounts_receivable', 'normal_balance' => 'debit', 'currency' => 'PKR']);
    $cash = Account::create(['company_id' => $company->id, 'code' => '1050', 'name' => 'Cash', 'type' => 'asset', 'subtype' => 'cash', 'normal_balance' => 'debit', 'currency' => 'PKR']);
    $revenue = Account::create(['company_id' => $company->id, 'code' => '4100', 'name' => 'Sales Revenue', 'type' => 'revenue', 'subtype' => 'other_income', 'normal_balance' => 'credit']);
    $customer = Customer::create(['company_id' => $company->id, 'customer_number' => 'C-1', 'name' => 'Truck owner', 'base_currency' => 'PKR', 'ar_account_id' => $ar->id, 'credit_limit' => 5000, 'is_active' => true]);

    foreach (['AR_INVOICE', 'AR_PAYMENT'] as $docType) {
        $template = PostingTemplate::create(['company_id' => $company->id, 'doc_type' => $docType, 'name' => $docType, 'is_active' => true, 'is_default' => true, 'effective_from' => '2026-01-01', 'version' => 1]);
        PostingTemplateLine::create(['template_id' => $template->id, 'role' => 'AR', 'account_id' => $ar->id]);
        if ($docType === 'AR_INVOICE') {
            PostingTemplateLine::create(['template_id' => $template->id, 'role' => 'REVENUE', 'account_id' => $revenue->id]);
        }
    }

    return compact('user', 'company', 'ar', 'cash', 'customer');
}

test('the statement shows a standalone invoice and a standalone payment that never touched a close, with a correct running balance', function () {
    $f = statementFixture();
    $invoice = app(CompanyContextService::class)->withContext($f['company'], fn () => app(CommandBus::class)->dispatch('invoice.create', [
        'customer' => $f['customer']->id, 'currency' => 'PKR', 'date' => '2026-09-10',
        'line_items' => [['description' => 'Fuel', 'quantity' => 1, 'unit_price' => 10000, 'tax_rate' => 0]],
    ], $f['user'], true));
    $invoiceModel = Invoice::findOrFail($invoice['data']['id']);
    // Sending moves it out of draft so it counts as a real receivable.
    app(CompanyContextService::class)->withContext($f['company'], fn () => app(CommandBus::class)->dispatch('invoice.send', ['id' => $invoiceModel->id], $f['user'], true));

    app(CompanyContextService::class)->withContext($f['company'], fn () => app(CommandBus::class)->dispatch('payment.create', [
        'invoice' => $invoiceModel->id, 'amount' => 4000, 'method' => 'cash', 'date' => '2026-09-12',
        'deposit_account_id' => $f['cash']->id, 'ar_account_id' => $f['ar']->id,
    ], $f['user'], true));

    $statement = app(CustomerStatementService::class)->statement($f['customer']->fresh());
    expect($statement['closing_balance'])->toBe(6000.0);

    $rows = collect($statement['rows']);
    expect($rows->first()['type'])->toBe('opening_balance');
    $invoiceRow = $rows->firstWhere('type', 'invoice');
    $paymentRow = $rows->firstWhere('type', 'payment');
    expect($invoiceRow)->not->toBeNull()->and((float) $invoiceRow['debit'])->toBe(10000.0)->and((float) $invoiceRow['balance'])->toBe(10000.0);
    expect($paymentRow)->not->toBeNull()->and((float) $paymentRow['credit'])->toBe(4000.0)->and((float) $paymentRow['balance'])->toBe(6000.0);
});

test('a blocked buyer is refused a new credit invoice through the fuel-sale service', function () {
    $f = statementFixture();
    $f['customer']->update(['is_credit_blocked' => true]);

    $item = \App\Modules\Inventory\Models\Item::create(['company_id' => $f['company']->id, 'sku' => 'PETROL', 'name' => 'Petrol', 'item_type' => 'product', 'unit_of_measure' => 'liter', 'currency' => 'PKR', 'avg_cost' => 250]);
    \App\Modules\FuelStation\Models\RateChange::create(['company_id' => $f['company']->id, 'item_id' => $item->id, 'effective_date' => '2020-01-01', 'purchase_rate' => 250, 'sale_rate' => 300]);
    app(\App\Services\CurrentCompany::class)->set($f['company']);

    expect(fn () => app(\App\Modules\FuelStation\Services\FuelSaleService::class)->createSale([
        'sale_type' => \App\Modules\FuelStation\Models\SaleMetadata::TYPE_CREDIT,
        'customer_id' => $f['customer']->id, 'item_id' => $item->id, 'quantity' => 5, 'sale_date' => '2026-09-15',
    ]))->toThrow(\InvalidArgumentException::class);
});

test('an over-limit credit sale is not blocked, only a blocked buyer is', function () {
    $f = statementFixture();
    // credit_limit is 5000; this sale is well over it but the buyer is not blocked.
    $item = \App\Modules\Inventory\Models\Item::create(['company_id' => $f['company']->id, 'sku' => 'PETROL', 'name' => 'Petrol', 'item_type' => 'product', 'unit_of_measure' => 'liter', 'currency' => 'PKR', 'avg_cost' => 250]);
    \App\Modules\FuelStation\Models\RateChange::create(['company_id' => $f['company']->id, 'item_id' => $item->id, 'effective_date' => '2020-01-01', 'purchase_rate' => 250, 'sale_rate' => 300]);
    app(\App\Services\CurrentCompany::class)->set($f['company']);

    $invoice = app(\App\Modules\FuelStation\Services\FuelSaleService::class)->createSale([
        'sale_type' => \App\Modules\FuelStation\Models\SaleMetadata::TYPE_CREDIT,
        'customer_id' => $f['customer']->id, 'item_id' => $item->id, 'quantity' => 100, 'sale_date' => '2026-09-15',
    ]);
    expect((float) $invoice->total_amount)->toBe(30000.0);
});
