<?php

use App\Http\Requests\CompanyStoreRequest;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

test('company creation exposes only ready industries and other', function () {
    $admin = new User;
    $admin->id = '00000000-0000-0000-0000-000000000000';
    $admin->name = 'Super Admin';
    $admin->username = 'industry-admin';
    $admin->email = 'industry-admin@example.com';
    $admin->password = 'password';
    $admin->save();

    $this->actingAs($admin)
        ->get(route('companies.create'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('companies/Create')
            ->where('industries', config('company-industries'))
            ->has('industries', 3));

    expect(collect(config('company-industries'))->pluck('code')->all())
        ->toBe(['fuel_station', 'travel', 'other']);
});

test('company creation rejects unfinished industry codes', function () {
    $request = new CompanyStoreRequest;
    $validator = validator(
        ['industry_code' => 'retail'],
        ['industry_code' => $request->rules()['industry_code']],
    );

    expect($validator->fails())->toBeTrue()
        ->and($validator->errors()->has('industry_code'))->toBeTrue();
});
