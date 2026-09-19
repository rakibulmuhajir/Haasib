<?php

use App\Facades\CompanyContext;
use App\Models\Company;
use App\Models\User;
use App\Services\CurrentCompany;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;

beforeEach(function () {
    Route::middleware(['web', 'auth', 'identify.company'])
        ->match(['GET', 'POST'], '/{company}/_tenant-authorization-test',
            fn () => response()->json(['company' => app(CurrentCompany::class)->id()]));
    Route::middleware(['web', 'auth', 'identify.company'])
        ->get('/_tenant-header-test', fn () => response()->json(['company' => app(CurrentCompany::class)->id()]));

    [$this->companyA, $this->userA, $this->companyB, $this->userB] = CompanyContext::crossCompany(function () {
        $a = Company::create(['name' => 'HTTP A', 'slug' => 'http-a', 'base_currency' => 'PKR']);
        $b = Company::create(['name' => 'HTTP B', 'slug' => 'http-b', 'base_currency' => 'PKR']);
        $userA = User::factory()->withoutTwoFactor()->create();
        $userB = User::factory()->withoutTwoFactor()->create();
        addCompanyMemberRow($a, $userA, 'owner');
        addCompanyMemberRow($b, $userB, 'owner');

        return [$a, $userA, $b, $userB];
    });
});

test('members cannot enter a foreign slug for reads or writes in either direction', function (string $method) {
    foreach ([[$this->userA, $this->companyA, $this->companyB], [$this->userB, $this->companyB, $this->companyA]] as [$user, $own, $foreign]) {
        $this->actingAs($user)->getJson("/{$own->slug}/_tenant-authorization-test")
            ->assertOk()->assertJsonPath('company', $own->id);
        // Deliberately leave stale foreign context in the same transactional session.
        CompanyContext::setContext($foreign);
        $this->actingAs($user)->{$method}("/{$foreign->slug}/_tenant-authorization-test")
            ->assertNotFound();
        expect(app(CurrentCompany::class)->id())->toBeNull()
            ->and(CompanyContext::getCompanyId())->toBeNull();
        expect(DB::selectOne("SELECT current_setting('app.current_company_id', true) AS value")->value)->toBe('');
        $this->getJson("/{$own->slug}/_tenant-authorization-test")->assertOk();
    }
})->with(['getJson', 'postJson']);

test('a company header cannot grant membership', function () {
    $this->actingAs($this->userA)->getJson('/_tenant-header-test', ['X-Company-Slug' => $this->companyB->slug])
        ->assertNotFound();
});

test('foreign customer detail URLs are rejected in both directions', function () {
    foreach ([[$this->userA, $this->companyB], [$this->userB, $this->companyA]] as [$user, $foreign]) {
        $customer = CompanyContext::crossCompany(fn () => CompanyContext::withContext($foreign,
            fn () => \App\Modules\Accounting\Models\Customer::create([
                'company_id' => $foreign->id,
                'customer_number' => 'HTTP-'.str()->random(8),
                'name' => 'Private foreign customer',
                'customer_type' => 'individual',
                'currency' => 'PKR',
            ])));
        $this->actingAs($user)->getJson("/{$foreign->slug}/customers/{$customer->id}")->assertNotFound();
    }
});

test('inactive membership cannot enter a company', function () {
    CompanyContext::crossCompany(function () {
        addCompanyMemberRow($this->companyB, $this->userA, 'owner');
        DB::table('auth.company_user')->where('company_id', $this->companyB->id)
            ->where('user_id', $this->userA->id)->update(['is_active' => false]);
    });
    $this->actingAs($this->userA)->getJson("/{$this->companyB->slug}/_tenant-authorization-test")->assertNotFound();
});

test('an active member of both companies can switch between them', function () {
    CompanyContext::crossCompany(fn () => addCompanyMemberRow($this->companyB, $this->userA, 'owner'));
    foreach ([$this->companyA, $this->companyB] as $company) {
        $this->actingAs($this->userA)->getJson("/{$company->slug}/_tenant-authorization-test")
            ->assertOk()->assertJsonPath('company', $company->id);
    }
});

test('guests cannot enter company routes', function () {
    $this->getJson("/{$this->companyA->slug}/_tenant-authorization-test")->assertUnauthorized();
});

test('god mode retains intentional access without membership', function () {
    $admin = User::factory()->withoutTwoFactor()->create(['id' => '00000000-0000-0000-0000-000000000001']);
    $this->actingAs($admin)->getJson("/{$this->companyB->slug}/_tenant-authorization-test")
        ->assertOk()->assertJsonPath('company', $this->companyB->id);
});
