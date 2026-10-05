<?php

use App\Models\Company;
use App\Models\User;
use App\Modules\Accounting\Models\Invoice;
use App\Services\CompanyContextService;
use App\Services\CompanyLetterhead;
use App\Services\CompanyRbacBootstrapper;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;

require_once __DIR__.'/FuelStation/CreditCloseFixtures.php';

/** An owner member of the fixture's company, so its settings and document pages open. */
function stampOwner(array $f): User
{
    $user = $f['user'];
    $company = $f['company'];
    DB::select("SELECT set_config('app.current_user_id', ?, false)", [$user->id]);
    DB::select("SELECT set_config('app.is_super_admin', 'true', false)");
    app(CompanyRbacBootstrapper::class)->bootstrap($company);
    DB::table('auth.company_user')->insert([
        'company_id' => $company->id, 'user_id' => $user->id, 'role' => 'owner',
        'joined_at' => now(), 'is_active' => true, 'created_at' => now(), 'updated_at' => now(),
    ]);
    app(CompanyContextService::class)->withContext($company, fn () => app(CompanyContextService::class)->assignRole($user, 'owner'));
    DB::select("SELECT set_config('app.is_super_admin', 'false', false)");
    enterCompany($company);

    return $user;
}

function stampInvoice(array $f, string $status): Invoice
{
    return Invoice::create([
        'company_id' => $f['company']->id, 'customer_id' => $f['customer']->id, 'invoice_number' => 'INV-ST-'.strtoupper($status),
        'invoice_date' => '2026-09-01', 'due_date' => '2026-09-01', 'status' => $status,
        'currency' => 'PKR', 'base_currency' => 'PKR', 'exchange_rate' => 1,
        'subtotal' => 1000, 'total_amount' => 1000, 'paid_amount' => 0, 'balance' => 1000,
    ]);
}

function stampUpload(array $f, User $user, array $data)
{
    return test()->actingAs($user)->patch("/{$f['company']->slug}/settings", $data);
}

test('company settings stores a stamp, a signature and the signer, and replaces the old file', function () {
    Storage::fake('public');
    $f = creditCloseFixture();
    $user = stampOwner($f);

    stampUpload($f, $user, [
        'stamp' => UploadedFile::fake()->image('stamp.png', 300, 300),
        'signature' => UploadedFile::fake()->image('sig.jpg', 200, 80),
        'signer_name' => 'Tariq Mahmood',
        'signer_title' => 'Manager',
    ])->assertSessionHasNoErrors();

    $company = Company::find($f['company']->id);
    expect($company->stamp_path)->toStartWith('company-stamps/'.$company->id.'/')
        ->and($company->signature_path)->toStartWith('company-signatures/'.$company->id.'/')
        ->and($company->signer_name)->toBe('Tariq Mahmood')
        ->and($company->signer_title)->toBe('Manager');
    Storage::disk('public')->assertExists($company->stamp_path);
    Storage::disk('public')->assertExists($company->signature_path);

    $old = $company->stamp_path;
    stampUpload($f, $user, ['stamp' => UploadedFile::fake()->image('new.png', 100, 100)])->assertSessionHasNoErrors();
    $company->refresh();
    expect($company->stamp_path)->not->toBe($old);
    Storage::disk('public')->assertMissing($old);

    stampUpload($f, $user, ['remove_stamp' => true, 'remove_signature' => true])->assertSessionHasNoErrors();
    $company->refresh();
    expect($company->stamp_path)->toBeNull()->and($company->signature_path)->toBeNull();
});

test('the letterhead exposes the stamp urls, signer and which documents carry it', function () {
    Storage::fake('public');
    $f = creditCloseFixture();
    $user = stampOwner($f);

    $before = app(CompanyLetterhead::class)->forCompany(Company::find($f['company']->id));
    expect($before['stampUrl'])->toBeNull()
        ->and($before['stampDocuments'])->toBe([
            'invoice' => true, 'consolidated_invoice' => true, 'statement' => true,
            'payment_receipt' => true, 'credit_note' => true, 'bill_payment' => false,
        ]);

    stampUpload($f, $user, [
        'stamp' => UploadedFile::fake()->image('stamp.png', 300, 300),
        'signature' => UploadedFile::fake()->image('sig.png', 200, 80),
        'signer_name' => 'Tariq', 'signer_title' => 'Manager',
        'stamp_documents' => ['invoice' => false, 'bill_payment' => true],
    ])->assertSessionHasNoErrors();

    $company = Company::find($f['company']->id);
    $letterhead = app(CompanyLetterhead::class)->forCompany($company);
    expect($letterhead['stampUrl'])->toBe('/storage/'.$company->stamp_path)
        ->and($letterhead['signatureUrl'])->toBe('/storage/'.$company->signature_path)
        ->and($letterhead['signerName'])->toBe('Tariq')
        ->and($letterhead['signerTitle'])->toBe('Manager')
        ->and($letterhead['stampDocuments']['invoice'])->toBeFalse()
        ->and($letterhead['stampDocuments']['bill_payment'])->toBeTrue()
        ->and($letterhead['stampDocuments']['credit_note'])->toBeTrue(); // never saved: default

    // The rule every page asks: ticked type, final status, something uploaded.
    $stamps = app(CompanyLetterhead::class);
    expect($stamps->stampFor($company, 'invoice'))->toBeNull()
        ->and($stamps->stampFor($company, 'credit_note')['stampUrl'])->toBe($letterhead['stampUrl'])
        ->and($stamps->stampFor($company, 'credit_note', false))->toBeNull()
        ->and($stamps->stampFor($company, 'bill_payment'))->not->toBeNull();
});

test('a posted invoice page carries the stamp; draft and void ones do not; unticking Invoices removes it', function () {
    Storage::fake('public');
    $f = creditCloseFixture();
    $user = stampOwner($f);
    stampUpload($f, $user, [
        'stamp' => UploadedFile::fake()->image('stamp.png', 300, 300),
        'signer_name' => 'Tariq',
    ])->assertSessionHasNoErrors();
    $company = Company::find($f['company']->id);

    $sent = stampInvoice($f, 'sent');
    $draft = stampInvoice($f, 'draft');
    $void = stampInvoice($f, 'void');
    $slug = $f['company']->slug;

    $this->actingAs($user)->get("/{$slug}/invoices/{$sent->id}")->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('stamp.stampUrl', '/storage/'.$company->stamp_path)
            ->where('stamp.signerName', 'Tariq'));

    $this->actingAs($user)->get("/{$slug}/invoices/{$draft->id}")->assertOk()
        ->assertInertia(fn (Assert $page) => $page->where('stamp', null));
    $this->actingAs($user)->get("/{$slug}/invoices/{$void->id}")->assertOk()
        ->assertInertia(fn (Assert $page) => $page->where('stamp', null));

    stampUpload($f, $user, ['stamp_documents' => ['invoice' => false]])->assertSessionHasNoErrors();
    $this->actingAs($user)->get("/{$slug}/invoices/{$sent->id}")->assertOk()
        ->assertInertia(fn (Assert $page) => $page->where('stamp', null));
});

test('stamp and signature uploads must be a small png, jpg or webp image', function () {
    Storage::fake('public');
    $f = creditCloseFixture();
    $user = stampOwner($f);

    stampUpload($f, $user, ['stamp' => UploadedFile::fake()->image('big.png', 200, 200)->size(2000)])
        ->assertSessionHasErrors('stamp');
    stampUpload($f, $user, ['signature' => UploadedFile::fake()->create('sig.pdf', 20, 'application/pdf')])
        ->assertSessionHasErrors('signature');
    stampUpload($f, $user, ['stamp' => UploadedFile::fake()->image('anim.gif', 50, 50)])
        ->assertSessionHasErrors('stamp');

    expect(Company::find($f['company']->id)->stamp_path)->toBeNull();
});
