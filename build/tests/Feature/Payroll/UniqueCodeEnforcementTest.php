<?php

use App\Modules\Payroll\Models\DeductionType;

/**
 * These uniqueness rules were written as `unique:pay.deduction_types,...`. Laravel reads the
 * part before the dot as a CONNECTION name, and config/database.php really does define one
 * called `pay`. So the check ran on a second database session, which under test cannot see
 * the row the test just inserted inside its own uncommitted transaction -- the rule passed
 * no matter what. It happened to work in production only because a real request is not
 * wrapped in a transaction.
 *
 * Routed through the model, the check runs on the same connection as the data. This test
 * exists to prove the rule actually fires; it fails against the old string form.
 *
 * Reuses payrollPageCompany() from PayrollEditPagesTest.php, which Pest loads when the
 * Payroll directory is run together (module scope), per project convention.
 */
test('a duplicate deduction type code is rejected within the same company', function () {
    [$user, $company] = payrollPageCompany();

    test()->post("/{$company->slug}/deduction-types", [
        'name' => 'Late arrival',
        'code' => 'LATE',
        'calculation_type' => 'fixed',
        'default_amount' => 100,
    ])->assertSessionHasNoErrors();

    expect(DeductionType::where('company_id', $company->id)->where('code', 'LATE')->count())->toBe(1);

    test()->post("/{$company->slug}/deduction-types", [
        'name' => 'Late arrival again',
        'code' => 'LATE',
        'calculation_type' => 'fixed',
        'default_amount' => 200,
    ])->assertSessionHasErrors('code');

    expect(DeductionType::where('company_id', $company->id)->where('code', 'LATE')->count())->toBe(1);
});

test('the same deduction type code is free in another company', function () {
    [, $companyA] = payrollPageCompany();

    test()->post("/{$companyA->slug}/deduction-types", [
        'name' => 'Late arrival',
        'code' => 'LATE',
        'calculation_type' => 'fixed',
        'default_amount' => 100,
    ])->assertSessionHasNoErrors();

    [, $companyB] = payrollPageCompany();

    test()->post("/{$companyB->slug}/deduction-types", [
        'name' => 'Late arrival',
        'code' => 'LATE',
        'calculation_type' => 'fixed',
        'default_amount' => 100,
    ])->assertSessionHasNoErrors();

    expect(DeductionType::where('company_id', $companyB->id)->where('code', 'LATE')->count())->toBe(1);
});
