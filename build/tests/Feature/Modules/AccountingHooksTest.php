<?php

use App\Models\Company;
use App\Models\User;
use App\Modules\Accounting\Events\InvoiceVoided;
use App\Modules\Accounting\Extensions\BillDefaults;
use App\Modules\Accounting\Extensions\InvoiceMutationGuard;
use App\Modules\Accounting\Models\Account;
use App\Modules\Accounting\Models\AccountingPeriod;
use App\Modules\Accounting\Models\Customer;
use App\Modules\Accounting\Models\FiscalYear;
use App\Modules\Accounting\Models\Invoice;
use App\Modules\Accounting\Models\PostingTemplate;
use App\Modules\Accounting\Models\PostingTemplateLine;
use App\Services\CommandBus;
use App\Services\CompanyContextService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;

function hooksFixture(): array
{
    $user = User::factory()->create();
    $company = Company::create(['name' => 'Hooks Co', 'slug' => 'hooks-co-'.str()->random(8), 'owner_id' => $user->id, 'base_currency' => 'PKR']);
    DB::statement("SELECT set_config('app.current_company_id', ?, false)", [$company->id]);
    $fy = FiscalYear::create(['company_id' => $company->id, 'name' => '2026', 'start_date' => '2026-01-01', 'end_date' => '2026-12-31', 'status' => 'open']);
    AccountingPeriod::create(['company_id' => $company->id, 'fiscal_year_id' => $fy->id, 'name' => 'September', 'period_number' => 9, 'start_date' => '2026-09-01', 'end_date' => '2026-09-30']);
    $ar = Account::create(['company_id' => $company->id, 'code' => '1100', 'name' => 'AR', 'type' => 'asset', 'subtype' => 'accounts_receivable', 'normal_balance' => 'debit', 'currency' => 'PKR']);
    $revenue = Account::create(['company_id' => $company->id, 'code' => '4100', 'name' => 'Sales Revenue', 'type' => 'revenue', 'subtype' => 'other_income', 'normal_balance' => 'credit']);
    $customer = Customer::create(['company_id' => $company->id, 'customer_number' => 'C-1', 'name' => 'Buyer', 'base_currency' => 'PKR', 'ar_account_id' => $ar->id, 'credit_limit' => 5000, 'is_active' => true]);

    $template = PostingTemplate::create(['company_id' => $company->id, 'doc_type' => 'AR_INVOICE', 'name' => 'AR_INVOICE', 'is_active' => true, 'is_default' => true, 'effective_from' => '2026-01-01', 'version' => 1]);
    PostingTemplateLine::create(['template_id' => $template->id, 'role' => 'AR', 'account_id' => $ar->id]);
    PostingTemplateLine::create(['template_id' => $template->id, 'role' => 'REVENUE', 'account_id' => $revenue->id]);

    return compact('user', 'company', 'ar', 'customer');
}

test('re-registering a hook under the same key replaces it', function () {
    // Only the test's own hook: the fuel guard would query without a company context.
    // Providers re-register theirs on the next test's boot.
    InvoiceMutationGuard::flushHooks();
    $calls = 0;
    InvoiceMutationGuard::extend('t', function () use (&$calls) {
        $calls++;
    });
    InvoiceMutationGuard::extend('t', function () use (&$calls) {
        $calls++;
    });
    app(InvoiceMutationGuard::class)->assert(new Invoice, 'update');
    expect($calls)->toBe(1);
    InvoiceMutationGuard::extend('t', fn () => null);
});

test('fuel registers its invoice guard, bill default and void listener', function () {
    expect(array_keys(InvoiceMutationGuard::hooks()))->toContain('fuel_station')
        ->and(array_keys(BillDefaults::hooks()))->toContain('fuel_station')
        ->and(Event::hasListeners(InvoiceVoided::class))->toBeTrue();
});

test('core accounting no longer imports FuelStation classes', function () {
    $hits = shell_exec('grep -rlE "Modules.FuelStation" '.base_path('modules/Accounting/Models/Invoice.php').' '.base_path('modules/Accounting/Actions/Invoice').' '.base_path('modules/Accounting/Http/Controllers/BillController.php').' '.base_path('modules/Accounting/Http/Controllers/CustomerController.php'));
    expect(trim((string) $hits))->toBe('');
});

test('a throwing void listener rolls the whole void back', function () {
    $f = hooksFixture();
    $ctx = app(CompanyContextService::class);
    $bus = app(CommandBus::class);

    $created = $ctx->withContext($f['company'], fn () => $bus->dispatch('invoice.create', [
        'customer' => $f['customer']->id, 'currency' => 'PKR', 'date' => '2026-09-10',
        'line_items' => [['description' => 'Service', 'quantity' => 1, 'unit_price' => 1000, 'tax_rate' => 0]],
    ], $f['user'], true));
    $invoiceId = $created['data']['id'];
    $ctx->withContext($f['company'], fn () => $bus->dispatch('invoice.send', ['id' => $invoiceId], $f['user'], true));

    Event::listen(InvoiceVoided::class, fn () => throw new RuntimeException('boom'));

    expect(fn () => $ctx->withContext($f['company'],
        fn () => $bus->dispatch('invoice.void', ['id' => $invoiceId, 'reason' => 'x'], $f['user'], true)))
        ->toThrow(RuntimeException::class);

    expect(Invoice::find($invoiceId)->status)->not->toBe('void');
});
