<?php

use App\Models\Company;
use App\Models\CompanyUser;
use App\Models\Invoice;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

use function Pest\Laravel\actingAs;

uses(RefreshDatabase::class);

describe('company scope', function () {
    it('restricts invoices to the authenticated company', function () {
        $user = User::factory()->create();
        $companyA = Company::factory()->create();
        $companyB = Company::factory()->create();

        CompanyUser::factory()->create([
            'company_id' => $companyA->id,
            'user_id' => $user->id,
            'role' => 'owner',
        ]);

        CompanyUser::factory()->create([
            'company_id' => $companyB->id,
            'user_id' => $user->id,
            'role' => 'owner',
        ]);

        $invoiceA = Invoice::factory()->create([
            'company_id' => $companyA->id,
            'balance_due' => 100,
        ]);

        $invoiceB = Invoice::factory()->create([
            'company_id' => $companyB->id,
            'balance_due' => 200,
        ]);

        $user->forceFill(['current_company_id' => $companyA->id])->save();
        actingAs($user);

        actingAs($user);

        $visibleInvoices = Invoice::all()->pluck('id')->all();
        expect($visibleInvoices)->toBe([$invoiceA->id]);

        $user->forceFill(['current_company_id' => $companyB->id])->save();

        actingAs($user);
        $visibleInvoices = Invoice::all()->pluck('id')->all();
        expect($visibleInvoices)->toBe([$invoiceB->id]);
    });

    it('allows bypassing the scope when explicitly requested', function () {
        $user = User::factory()->create();
        $companyA = Company::factory()->create();
        $companyB = Company::factory()->create();

        CompanyUser::factory()->create([
            'company_id' => $companyA->id,
            'user_id' => $user->id,
            'role' => 'owner',
        ]);

        CompanyUser::factory()->create([
            'company_id' => $companyB->id,
            'user_id' => $user->id,
            'role' => 'owner',
        ]);

        Invoice::factory()->create([
            'company_id' => $companyA->id,
            'balance_due' => 100,
        ]);

        Invoice::factory()->create([
            'company_id' => $companyB->id,
            'balance_due' => 300,
        ]);

        actingAs($user);
        $user->forceFill(['current_company_id' => $companyA->id])->save();

        expect(Invoice::count())->toBe(1);
        expect(Invoice::withoutCompanyScope()->count())->toBe(2);
    });
});
