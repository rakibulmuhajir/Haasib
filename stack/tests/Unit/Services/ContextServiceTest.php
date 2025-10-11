<?php

use App\Services\ContextService;
use App\Models\User;
use App\Models\Company;
use App\Models\CompanyUser;

covers(ContextService::class);

it('can get current user from request', function () {
    $user = User::factory()->create();
    
    $request = createRequest();
    $request->setUserResolver(fn () => $user);

    $contextService = new ContextService();
    $currentUser = $contextService->getCurrentUser($request);

    expect($currentUser->id)->toBe($user->id);
});

it('returns null when no user authenticated', function () {
    $request = createRequest();
    $request->setUserResolver(fn () => null);

    $contextService = new ContextService();
    $currentUser = $contextService->getCurrentUser($request);

    expect($currentUser)->toBeNull();
});

it('can get current company from request attributes', function () {
    $company = Company::factory()->create();
    
    $request = createRequest();
    $request->attributes->set('company', $company);

    $contextService = new ContextService();
    $currentCompany = $contextService->getCurrentCompany($request);

    expect($currentCompany->id)->toBe($company->id);
});

it('resolves active company from session when not in request', function () {
    $user = User::factory()->create();
    $company = Company::factory()->create();

    CompanyUser::create([
        'company_id' => $company->id,
        'user_id' => $user->id,
        'role' => 'owner',
        'is_active' => true,
    ]);

    $request = createRequest();
    $request->setUserResolver(fn () => $user);
    session(['active_company_id' => $company->id]);

    $contextService = new ContextService();
    $resolvedCompany = $contextService->resolveActiveCompany($request, $user);

    expect($resolvedCompany->id)->toBe($company->id);
});

it('resolves to user\'s default company when session empty', function () {
    $user = User::factory()->create();
    $defaultCompany = Company::factory()->create();
    $otherCompany = Company::factory()->create();

    CompanyUser::create([
        'company_id' => $defaultCompany->id,
        'user_id' => $user->id,
        'role' => 'owner',
        'is_active' => true,
    ]);

    CompanyUser::create([
        'company_id' => $otherCompany->id,
        'user_id' => $user->id,
        'role' => 'member',
        'is_active' => true,
    ]);

    $request = createRequest();
    $request->setUserResolver(fn () => $user);
    // No active company in session

    $contextService = new ContextService();
    $resolvedCompany = $contextService->resolveActiveCompany($request, $user);

    expect($resolvedCompany->id)->toBe($defaultCompany->id); // First company
});

it('returns null when user has no companies', function () {
    $user = User::factory()->create();

    $request = createRequest();
    $request->setUserResolver(fn () => $user);

    $contextService = new ContextService();
    $resolvedCompany = $contextService->resolveActiveCompany($request, $user);

    expect($resolvedCompany)->toBeNull();
});

it('can set company context in request', function () {
    $user = User::factory()->create();
    $company = Company::factory()->create();

    $request = createRequest();
    $request->setUserResolver(fn () => $user);

    $contextService = new ContextService();
    $contextService->setCompanyContext($request, $company);

    expect($request->attributes->get('company')->id)->toBe($company->id);
    expect(session('active_company_id'))->toBe($company->id);
});

it('can get user permissions for current company', function () {
    $user = User::factory()->create(['role' => 'company_owner']);
    $company = Company::factory()->create();

    CompanyUser::create([
        'company_id' => $company->id,
        'user_id' => $user->id,
        'role' => 'owner',
        'is_active' => true,
    ]);

    $request = createRequest();
    $request->setUserResolver(fn () => $user);
    $request->attributes->set('company', $company);

    $contextService = new ContextService();
    $permissions = $contextService->getUserPermissions($request);

    expect($permissions['role'])->toBe('owner');
    expect($permissions['can_switch_company'])->toBeTrue();
});

it('can validate user access to company', function () {
    $user = User::factory()->create();
    $company = Company::factory()->create();
    $otherCompany = Company::factory()->create();

    CompanyUser::create([
        'company_id' => $company->id,
        'user_id' => $user->id,
        'role' => 'owner',
        'is_active' => true,
    ]);

    $contextService = new ContextService();

    expect($contextService->userHasAccessToCompany($user, $company->id))->toBeTrue();
    expect($contextService->userHasAccessToCompany($user, $otherCompany->id))->toBeFalse();
    expect($contextService->userHasAccessToCompany($user, 999))->toBeFalse();
});

it('can get company context summary', function () {
    $user = User::factory()->create(['role' => 'company_owner']);
    $company = Company::factory()->create(['name' => 'Test Company', 'base_currency' => 'USD']);

    CompanyUser::create([
        'company_id' => $company->id,
        'user_id' => $user->id,
        'role' => 'owner',
        'is_active' => true,
    ]);

    $request = createRequest();
    $request->setUserResolver(fn () => $user);
    $request->attributes->set('company', $company);

    $contextService = new ContextService();
    $summary = $contextService->getCompanyContextSummary($request);

    expect($summary['company']['id'])->toBe($company->id);
    expect($summary['company']['name'])->toBe('Test Company');
    expect($summary['company']['base_currency'])->toBe('USD');
    expect($summary['user_role'])->toBe('owner');
    expect($summary['permissions']['role'])->toBe('owner');
});

it('handles missing company context gracefully', function () {
    $user = User::factory()->create();

    $request = createRequest();
    $request->setUserResolver(fn () => $user);

    $contextService = new ContextService();
    $summary = $contextService->getCompanyContextSummary($request);

    expect($summary['company'])->toBeNull();
    expect($summary['user_role'])->toBeNull();
    expect($summary['permissions'])->toBeNull();
});