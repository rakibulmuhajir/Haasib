<?php

use App\Models\Company;
use App\Models\User;
use App\Modules\Accounting\Models\Account;
use App\Modules\Accounting\Models\AccountingPeriod;
use App\Modules\Accounting\Models\FiscalYear;
use App\Modules\Accounting\Models\Transaction;
use App\Modules\Accounting\Models\TransactionAttachment;
use App\Services\CompanyContextService;
use App\Services\CompanyRbacBootstrapper;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

/**
 * An expense with no document is an assertion; an expense with the bill attached is
 * evidence. The expense form accepted a description and an amount and nothing else, so
 * the paper stayed in a drawer and the books could not be checked against it.
 *
 * The file goes on the PRIVATE disk and is served only through a company-scoped
 * controller: a supplier invoice carries account numbers and pricing, and has no
 * business being fetchable by guessing a URL.
 */
function expenseAttachmentFixture(): array
{
    Storage::fake('local');

    // Mirrors ledgerIndexFixture(), the fixture the other HTTP-level Accounting tests use:
    // withoutTwoFactor so an authenticated POST is not bounced to the 2FA challenge, and
    // app.current_user_id set before anything is written.
    $user = User::factory()->withoutTwoFactor()->create();
    $company = Company::create(['name' => 'Receipt Co', 'slug' => 'receipt-co-'.str()->lower(str()->random(10)), 'base_currency' => 'PKR']);
    DB::select("SELECT set_config('app.current_user_id', ?, false)", [$user->id]);
    DB::select("SELECT set_config('app.is_super_admin', 'true', false)");
    DB::statement("SELECT set_config('app.current_company_id', ?, false)", [$company->id]);
    $fy = FiscalYear::create(['company_id' => $company->id, 'name' => '2026', 'start_date' => '2026-01-01', 'end_date' => '2026-12-31', 'status' => 'open']);
    AccountingPeriod::create(['company_id' => $company->id, 'fiscal_year_id' => $fy->id, 'name' => 'September', 'period_number' => 9, 'start_date' => '2026-09-01', 'end_date' => '2026-09-30']);

    $expenseAccount = Account::create(['company_id' => $company->id, 'code' => '6100', 'name' => 'Electricity', 'type' => 'expense', 'subtype' => 'expense', 'normal_balance' => 'debit', 'is_active' => true]);
    $cash = Account::create(['company_id' => $company->id, 'code' => '1050', 'name' => 'Cash', 'type' => 'asset', 'subtype' => 'cash', 'normal_balance' => 'debit', 'currency' => 'PKR', 'is_active' => true]);

    app(CompanyRbacBootstrapper::class)->bootstrap($company);
    DB::table('auth.company_user')->insert([
        'company_id' => $company->id, 'user_id' => $user->id, 'role' => 'owner',
        'joined_at' => now(), 'is_active' => true, 'created_at' => now(), 'updated_at' => now(),
    ]);
    app(CompanyContextService::class)->withContext($company, fn () => app(CompanyContextService::class)->assignRole($user, 'owner'));
    DB::select("SELECT set_config('app.is_super_admin', 'false', false)");
    test()->actingAs($user);
    app(\App\Services\CurrentCompany::class)->set($company);

    return compact('user', 'company', 'expenseAccount', 'cash');
}

function expensePayload(array $f): array
{
    return [
        'date' => '2026-09-15',
        'account_id' => $f['expenseAccount']->id,
        'amount' => 18500,
        'paid_from_account_id' => $f['cash']->id,
        'description' => 'Electricity bill September',
    ];
}

test('an expense keeps the bill that was attached to it', function () {
    $f = expenseAttachmentFixture();

    $response = test()->actingAs($f['user'])
        ->post("/{$f['company']->slug}/expenses", expensePayload($f) + [
            'attachment' => UploadedFile::fake()->create('k-electric-september.pdf', 200, 'application/pdf'),
        ]);

    // A failed post also redirects (back, with an error), so a bare assertRedirect proves
    // nothing. Name the reason instead.
    expect(session('error'))->toBeNull();
    $response->assertSessionHasNoErrors()->assertRedirect();

    $attachment = TransactionAttachment::where('company_id', $f['company']->id)->sole();
    expect($attachment->original_name)->toBe('k-electric-september.pdf')
        ->and($attachment->disk)->toBe('local')
        ->and($attachment->uploaded_by_user_id)->toBe($f['user']->id);

    // Attached to the expense journal, not floating free.
    $transaction = Transaction::where('company_id', $f['company']->id)->where('transaction_type', 'expense')->sole();
    expect($attachment->transaction_id)->toBe($transaction->id);

    Storage::disk('local')->assertExists($attachment->path);
});

test('the document is stored privately, never under the public disk', function () {
    $f = expenseAttachmentFixture();

    test()->actingAs($f['user'])
        ->post("/{$f['company']->slug}/expenses", expensePayload($f) + [
            'attachment' => UploadedFile::fake()->image('receipt.jpg'),
        ])
        ->assertRedirect();

    $attachment = TransactionAttachment::where('company_id', $f['company']->id)->sole();

    // Namespaced per company so a disk-level mistake cannot cross tenants.
    expect($attachment->path)->toContain($f['company']->id)
        ->and($attachment->path)->not->toContain('public');
});

test('an expense without a bill still records perfectly well', function () {
    $f = expenseAttachmentFixture();

    $response = test()->actingAs($f['user'])
        ->post("/{$f['company']->slug}/expenses", expensePayload($f));

    expect(session('error'))->toBeNull();
    $response->assertSessionHasNoErrors()->assertRedirect();

    expect(Transaction::where('company_id', $f['company']->id)->where('transaction_type', 'expense')->count())->toBe(1)
        ->and(TransactionAttachment::where('company_id', $f['company']->id)->count())->toBe(0);
});

test('an executable is refused', function () {
    $f = expenseAttachmentFixture();

    test()->actingAs($f['user'])
        ->post("/{$f['company']->slug}/expenses", expensePayload($f) + [
            'attachment' => UploadedFile::fake()->create('invoice.exe', 10, 'application/octet-stream'),
        ])
        ->assertSessionHasErrors('attachment');

    expect(TransactionAttachment::where('company_id', $f['company']->id)->count())->toBe(0);
});

test('the download route serves the document back under its own name', function () {
    $f = expenseAttachmentFixture();

    test()->actingAs($f['user'])
        ->post("/{$f['company']->slug}/expenses", expensePayload($f) + [
            'attachment' => UploadedFile::fake()->create('k-electric-september.pdf', 120, 'application/pdf'),
        ])
        ->assertRedirect();

    $attachment = TransactionAttachment::where('company_id', $f['company']->id)->sole();

    test()->actingAs($f['user'])
        ->get("/{$f['company']->slug}/expenses/attachments/{$attachment->id}")
        ->assertOk()
        ->assertDownload('k-electric-september.pdf');
});
