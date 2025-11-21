<?php

use Illuminate\Support\Facades\Route;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Laravel\Fortify\Features;

Route::get('/', function () {
    return Inertia::render('Welcome', [
        'canRegister' => Features::enabled(Features::registration()),
    ]);
})->name('home');

Route::get('dashboard', function () {
    return Inertia::render('Dashboard');
})->middleware(['auth', 'verified'])->name('dashboard');

Route::get('dashboard/custom', function () {
    return Inertia::render('DashboardCustom');
})->middleware(['auth', 'verified'])->name('dashboard.custom');

Route::get('customers', function () {
    return Inertia::render('Customers');
})->middleware(['auth', 'verified'])->name('customers');

Route::get('invoices', function () {
    return Inertia::render('Invoices');
})->middleware(['auth', 'verified'])->name('invoices');

Route::get('companies', function () {
    // Query actual companies from database
    $companies = App\Models\Company::with('creator')
        ->orderBy('created_at', 'desc')
        ->get()
        ->map(function ($company) {
            return [
                'id' => $company->id,
                'name' => $company->name,
                'email' => $company->creator?->email ?: 'no-email@company.com',
                'industry' => $company->industry ?: 'Other',
                'country' => $company->country ?: 'US',
                'base_currency' => $company->base_currency,
                'created_at' => $company->created_at->toISOString(),
                'updated_at' => $company->updated_at->toISOString()
            ];
        });

    return Inertia::render('Companies', [
        'companies' => $companies,
        'activeCompanyId' => session('active_company_id')
    ]);
})->middleware(['auth', 'verified'])->name('companies');

Route::patch('companies/{company}/activate', function (App\Models\Company $company) {
    // Set the active company in session or user context
    session(['active_company_id' => $company->id]);
    
    return response()->json([
        'success' => true,
        'message' => 'Company activated successfully',
        'activeCompanyId' => $company->id
    ]);
})->middleware(['auth', 'verified'])->name('companies.activate');

Route::get('companies/create', function () {
    return Inertia::render('Companies/Create');
})->middleware(['auth', 'verified'])->name('companies.create');

Route::get('companies/{company}', function (App\Models\Company $company) {
    $company->load('creator');
    return Inertia::render('Companies/Show', [
        'company' => [
            'id' => $company->id,
            'name' => $company->name,
            'email' => $company->creator?->email ?: 'no-email@company.com',
            'industry' => $company->industry ?: 'Other',
            'country' => $company->country ?: 'US',
            'base_currency' => $company->base_currency,
            'created_at' => $company->created_at->toISOString(),
            'updated_at' => $company->updated_at->toISOString()
        ]
    ]);
})->middleware(['auth', 'verified'])->name('companies.show');

Route::get('companies/{company}/edit', function (App\Models\Company $company) {
    return Inertia::render('Companies/Edit', [
        'company' => [
            'id' => $company->id,
            'name' => $company->name,
            'industry' => $company->industry,
            'country' => $company->country,
            'base_currency' => $company->base_currency,
            'settings' => $company->settings
        ]
    ]);
})->middleware(['auth', 'verified'])->name('companies.edit');

Route::put('companies/{company}', function (App\Models\Company $company) {
    $validated = request()->validate([
        'name' => 'required|string|max:255',
        'industry' => 'required|string',
        'country' => 'required|string',
        'base_currency' => 'required|string|size:3',
        'settings' => 'nullable|array'
    ]);

    $company->update($validated);

    return redirect()->route('companies')
        ->with('success', "Company '{$company->name}' updated successfully!");
})->middleware(['auth', 'verified'])->name('companies.update');

Route::delete('companies/{company}', function (App\Models\Company $company) {
    $companyName = $company->name;
    $company->delete();
    
    return redirect()->route('companies')
        ->with('success', "Company '{$companyName}' deleted successfully!");
})->middleware(['auth', 'verified'])->name('companies.destroy');

// API routes for company user management
Route::get('api/companies/{company}/users', function (App\Models\Company $company) {
    $companyUsers = App\Models\CompanyUser::with('user')
        ->where('company_id', $company->id)
        ->orderBy('created_at', 'desc')
        ->get();

    return response()->json([
        'data' => $companyUsers->map(function ($companyUser) {
            return [
                'id' => $companyUser->id,
                'user_id' => $companyUser->user_id,
                'company_id' => $companyUser->company_id,
                'role' => $companyUser->role,
                'is_active' => $companyUser->is_active,
                'joined_at' => $companyUser->created_at,
                'user' => [
                    'id' => $companyUser->user->id,
                    'name' => $companyUser->user->name,
                    'email' => $companyUser->user->email,
                ]
            ];
        })
    ]);
})->middleware(['auth', 'verified']);

Route::post('api/companies/{company}/users', function (App\Models\Company $company) {
    $validated = request()->validate([
        'email' => 'required|email',
        'role' => 'required|in:owner,admin,member,viewer'
    ]);

    // Get the currently authenticated user
    $currentUser = auth()->user();
    
    // Check if user is trying to assign themselves
    if (strtolower($validated['email']) === strtolower($currentUser->email)) {
        return response()->json(['message' => 'You cannot assign yourself to the company'], 422);
    }

    // Find user by email using raw query to check exists in auth.users
    $user = \DB::connection('pgsql')
        ->table('auth.users')
        ->where('email', $validated['email'])
        ->first();

    if (!$user) {
        return response()->json(['message' => 'User not found'], 404);
    }

    // Get the User model instance
    $userModel = App\Models\User::find($user->id);

    // Check if user is already assigned to company
    $existingAssignment = App\Models\CompanyUser::where('company_id', $company->id)
        ->where('user_id', $userModel->id)
        ->first();

    if ($existingAssignment) {
        return response()->json(['message' => 'User is already assigned to this company'], 422);
    }

    // Create assignment using direct database insert
    \DB::connection('pgsql')
        ->table('auth.company_user')
        ->insert([
            'id' => (string)\Str::uuid(),
            'company_id' => $company->id,
            'user_id' => $userModel->id,
            'role' => $validated['role'],
            'is_active' => true,
            'joined_at' => now(),
            'created_at' => now(),
            'updated_at' => now()
        ]);

    return response()->json([
        'message' => 'User assigned successfully',
        'data' => [
            'user_id' => $userModel->id,
            'email' => $userModel->email,
            'role' => $validated['role']
        ]
    ]);
})->middleware(['auth', 'verified']);

Route::delete('api/companies/{company}/users/{user}', function (App\Models\Company $company, App\Models\User $user) {
    $companyUser = App\Models\CompanyUser::where('company_id', $company->id)
        ->where('user_id', $user->id)
        ->first();

    if (!$companyUser) {
        return response()->json(['message' => 'User assignment not found'], 404);
    }

    $companyUser->delete();

    return response()->json(['message' => 'User removed successfully']);
})->middleware(['auth', 'verified']);

Route::post('companies', function () {
    // Basic validation
    $validated = request()->validate([
        'name' => 'required|string|max:255',
        'industry' => 'required|string',
        'country' => 'required|string',
        'base_currency' => 'required|string|size:3',
        'timezone' => 'nullable|string',
    ]);

    // Create the company
    $company = App\Models\Company::create([
        'name' => $validated['name'],
        'industry' => $validated['industry'],
        'country' => $validated['country'],
        'base_currency' => $validated['base_currency'],
        'language' => 'en',
        'locale' => 'en_US',
        'created_by_user_id' => auth()->id(),
        'is_active' => true,
        'settings' => [
            'timezone' => $validated['timezone'] ?? 'UTC'
        ]
    ]);

    return redirect()->route('companies')
        ->with('success', "Company '{$company->name}' created successfully!");
})->middleware(['auth', 'verified'])->name('companies.store');

require __DIR__.'/settings.php';

