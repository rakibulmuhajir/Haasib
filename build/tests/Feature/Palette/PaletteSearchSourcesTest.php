<?php

use App\Models\Company;
use App\Models\User;
use App\Modules\Accounting\Models\Vendor;
use Illuminate\Support\Facades\DB;

function searchCompany(string $name): Company
{
    $owner = User::factory()->create();
    $company = Company::create([
        'name' => $name, 'slug' => 'srch-'.str()->lower(str()->random(8)),
        'owner_id' => $owner->id, 'base_currency' => 'PKR',
    ]);
    enterCompany($company);
    DB::table('auth.company_user')->insert([
        'company_id' => $company->id, 'user_id' => $owner->id, 'role' => 'owner',
        'joined_at' => now(), 'is_active' => true, 'created_at' => now(), 'updated_at' => now(),
    ]);

    $company->setRelation('owner', $owner);

    return $company;
}

test('vendor search finds by name within the company only', function () {
    $a = searchCompany('A');
    Vendor::create(['company_id' => $a->id, 'vendor_number' => 'V-1', 'name' => 'Acme Supplies', 'base_currency' => 'PKR']);
    $b = searchCompany('B');
    Vendor::create(['company_id' => $b->id, 'vendor_number' => 'V-2', 'name' => 'Acme Elsewhere', 'base_currency' => 'PKR']);
    enterCompany($a);

    $res = $this->actingAs($a->owner)
        ->withHeader("X-Company-Slug", $a->slug)->getJson("/api/palette/flag-values?search_entity=vendor&q=acme")->assertOk();

    expect(collect($res->json('values'))->pluck('label')->all())->toBe(['Acme Supplies']);
});

test('an unknown search entity returns nothing', function () {
    $a = searchCompany('C');

    $this->actingAs($a->owner)
        ->withHeader("X-Company-Slug", $a->slug)->getJson("/api/palette/flag-values?search_entity=nonsense&q=x")
        ->assertOk()->assertJson(['values' => []]);
});
