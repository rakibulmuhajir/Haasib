<?php

use Illuminate\Support\Facades\Route;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Laravel\Fortify\Features;
use Illuminate\Validation\Rule as ValidationRule;
use Modules\Accounting\Models\Customer;
use Modules\Accounting\Models\Invoice;
use Illuminate\Http\Request;

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
    $companyId = session('active_company_id') ?? session('current_company_id');

    $customers = Customer::query()
        ->when($companyId, fn ($q) => $q->where('company_id', $companyId))
        ->select('id', 'customer_number', 'name', 'email', 'status', 'created_at')
        ->orderBy('name')
        ->limit(50)
        ->get();

    return Inertia::render('Accounting/Customers', [
        'customers' => $customers,
    ]);
})->middleware(['auth', 'verified'])->name('customers');

Route::post('customers', function (Request $request) {
    $companyId = session('active_company_id') ?? session('current_company_id');
    if (! $companyId) {
        return back()->with('error', 'Select a company before creating customers.');
    }

    $validated = $request->validate([
        'name' => ['required', 'string', 'max:255'],
        'email' => ['nullable', 'email', 'max:255'],
    ]);

    $nextNumber = Customer::query()
        ->where('company_id', $companyId)
        ->max(DB::raw("CAST(SUBSTRING(customer_number, 6) AS INTEGER)")) ?? 0;
    $customerNumber = 'CUST-' . str_pad((string) ($nextNumber + 1), 5, '0', STR_PAD_LEFT);

    Customer::create([
        'company_id' => $companyId,
        'customer_number' => $customerNumber,
        'name' => $validated['name'],
        'email' => $validated['email'] ?? null,
        'status' => 'active',
        'created_by' => $request->user()->id,
    ]);

    return redirect()->route('customers')->with('success', 'Customer created.');
})->middleware(['auth', 'verified'])->name('customers.store');

Route::get('invoices', function () {
    $companyId = session('active_company_id') ?? session('current_company_id');

    $invoices = Invoice::query()
        ->when($companyId, fn ($q) => $q->where('company_id', $companyId))
        ->with('customer:id,name')
        ->select('id', 'invoice_number', 'customer_id', 'total_amount', 'status', 'due_date', 'created_at')
        ->orderByDesc('created_at')
        ->limit(50)
        ->get()
        ->map(function ($invoice) {
            return [
                'id' => $invoice->id,
                'invoice_number' => $invoice->invoice_number,
                'customer_name' => $invoice->customer?->name,
                'total_amount' => $invoice->total_amount,
                'status' => $invoice->status,
                'due_date' => optional($invoice->due_date)->toDateString(),
            ];
        });

    return Inertia::render('Accounting/Invoices', [
        'invoices' => $invoices,
    ]);
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

// Company Switch Routes (copied from stack working pattern)
Route::post('/company/{company}/switch', [App\Http\Controllers\CompanyController::class, 'switch'])
    ->middleware(['auth', 'verified'])
    ->name('company.switch');

Route::post('/company/set-first', [App\Http\Controllers\CompanyController::class, 'setFirstCompany'])
    ->middleware(['auth', 'verified'])
    ->name('company.set-first');

Route::post('/company/clear-context', [App\Http\Controllers\CompanyController::class, 'clearContext'])
    ->middleware(['auth', 'verified'])
    ->name('company.clear-context');

Route::get('api/company/status', [App\Http\Controllers\CompanyController::class, 'status'])
    ->middleware(['auth', 'verified'])
    ->name('api.company.status');

// Company context debugging and monitoring
Route::prefix('api/company-context')->middleware(['auth', 'verified'])->group(function () {
    Route::get('health', [App\Http\Controllers\CompanyContextHealthController::class, 'healthCheck'])
        ->name('api.company-context.health');
    
    Route::get('metrics', [App\Http\Controllers\CompanyContextHealthController::class, 'metrics'])
        ->name('api.company-context.metrics');
    
    Route::post('test-resolution', [App\Http\Controllers\CompanyContextHealthController::class, 'testResolution'])
        ->name('api.company-context.test-resolution');
});

Route::get('companies/create', function () {
    return Inertia::render('Companies/Create');
})->middleware(['auth', 'verified'])->name('companies.create');

Route::get('companies/{company}', function (App\Models\Company $company) {
    session(['active_company_id' => $company->id]);
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
    session(['active_company_id' => $company->id]);
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
    
    // Clear active company session if this was the active company
    if (session('active_company_id') === $company->id) {
        session()->forget('active_company_id');
    }
    
    $company->delete();
    
    return redirect()->route('companies')
        ->with('success', "Company '{$companyName}' deleted successfully!");
})->middleware(['auth', 'verified'])->name('companies.destroy');

// API routes for company user management
// Explicit GET/POST for company users
Route::match(['get', 'head'], 'api/companies/{company}/users', function (App\Models\Company $company) {
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
    $data = request()->validate([
        'user_id' => ['nullable', 'uuid'],
        'email' => ['nullable', 'email'],
        'role' => ['nullable', 'string', 'max:255'],
    ]);

    $role = $data['role'] ?? 'member';

    try {
        $userId = $data['user_id'] ?? null;
        if (!$userId && !empty($data['email'])) {
            $userId = \App\Models\User::where('email', $data['email'])->value('id');
        }

        if (!$userId) {
            return response()->json([
                'message' => 'No user found with that email. Invite them first, then assign.',
                'errors' => ['user' => ['User not found']],
            ], 404);
        }

        $existing = App\Models\CompanyUser::where('company_id', $company->id)
            ->where('user_id', $userId)
            ->first();

        if ($existing) {
            return response()->json([
                'message' => 'User is already assigned. Update their role from the list.',
                'errors' => ['user' => ['Already assigned']],
            ], 409);
        }
    } catch (\Throwable $e) {
        return response()->json([
            'message' => 'Unable to resolve user. Please check the database.',
            'errors' => ['user' => [$e->getMessage()]],
        ], 500);
    }

    $pivot = App\Models\CompanyUser::updateOrCreate(
        [
            'company_id' => $company->id,
            'user_id' => $userId,
        ],
        [
            'id' => \Illuminate\Support\Str::uuid()->toString(),
            'role' => $role,
            'is_active' => true,
            'joined_at' => now(),
        ]
    );

    return response()->json([
        'message' => 'User assigned to company successfully',
        'data' => [
            'company_id' => $pivot->company_id,
            'user_id' => $pivot->user_id,
            'role' => $pivot->role,
            'is_active' => $pivot->is_active,
        ],
    ], 201);
})->middleware(['auth', 'verified']);

Route::match(['patch', 'post'], 'api/companies/{company}/users/{user}', function (App\Models\Company $company, App\Models\User $user) {
    $data = request()->validate([
        'role' => ['required', 'string', 'max:255'],
    ]);

    $pivot = App\Models\CompanyUser::where('company_id', $company->id)
        ->where('user_id', $user->id)
        ->first();

    if (!$pivot) {
        return response()->json([
            'message' => 'User is not assigned to this company.',
        ], 404);
    }

    if ($pivot->role === 'company_owner') {
        return response()->json([
            'message' => 'Company owner role cannot be changed. Contact a system admin.',
        ], 403);
    }

    $pivot->role = $data['role'];
    $pivot->save();

    return response()->json([
        'message' => 'Role updated successfully',
        'data' => [
            'company_id' => $pivot->company_id,
            'user_id' => $pivot->user_id,
            'role' => $pivot->role,
            'is_active' => $pivot->is_active,
        ],
    ]);
})->middleware(['auth', 'verified']);

Route::post('api/companies/{company}/invite', function (App\Models\Company $company) {
    $validated = request()->validate([
        'email' => 'required|email',
        'role' => 'required|in:company_owner,company_admin,accounting_admin,accounting_operator,accounting_viewer,portal_customer,portal_vendor'
    ]);

    $currentUser = auth()->user();
    $invitationService = new App\Services\InvitationService();
    
    try {
        $invitation = $invitationService->sendInvitation(
            $company,
            $validated['email'],
            $validated['role'],
            $currentUser
        );

        return response()->json([
            'message' => 'Invitation sent successfully',
            'data' => [
                'invitation_id' => $invitation->id,
                'email' => $invitation->email,
                'role' => $invitation->role,
                'expires_at' => $invitation->expires_at,
                'status' => $invitation->status
            ]
        ], 201);

    } catch (\Illuminate\Validation\ValidationException $e) {
        return response()->json([
            'message' => 'Validation failed',
            'errors' => $e->errors()
        ], 422);
    } catch (\Exception $e) {
        return response()->json([
            'message' => 'Failed to send invitation',
            'error' => $e->getMessage()
        ], 500);
    }
})->middleware(['auth', 'verified']);

Route::delete('api/companies/{company}/users/{user}', function (App\Models\Company $company, App\Models\User $user) {
    $companyUser = App\Models\CompanyUser::where('company_id', $company->id)
        ->where('user_id', $user->id)
        ->first();

    if (!$companyUser) {
        return response()->json(['message' => 'User assignment not found'], 404);
    }

    if ($companyUser->role === 'company_owner') {
        return response()->json([
            'message' => 'Company owner cannot be removed. Contact a system admin.',
        ], 403);
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

// Invitation acceptance/decline routes
Route::get('/invitations/accept/{token}', function (string $token) {
    $invitationService = new App\Services\InvitationService();
    
    if (!auth()->check()) {
        // Store the token in session and redirect to login
        session(['invitation_token' => $token]);
        return redirect()->route('login')
            ->with('info', 'Please log in to accept your invitation.');
    }
    
    try {
        $success = $invitationService->acceptInvitation($token, auth()->user());
        
        if ($success) {
            return redirect()->route('dashboard')
                ->with('success', 'Invitation accepted! Welcome to the team.');
        } else {
            return redirect()->route('dashboard')
                ->with('error', 'This invitation is no longer valid.');
        }
    } catch (\Illuminate\Validation\ValidationException $e) {
        return redirect()->route('dashboard')
            ->with('error', $e->getMessage());
    } catch (\Exception $e) {
        return redirect()->route('dashboard')
            ->with('error', 'There was an error accepting the invitation.');
    }
})->name('invitations.accept');

Route::get('/invitations/decline/{token}', function (string $token) {
    $invitationService = new App\Services\InvitationService();
    
    try {
        $success = $invitationService->declineInvitation($token);
        
        if ($success) {
            return view('invitations.declined')
                ->with('message', 'You have successfully declined the invitation.');
        } else {
            return view('invitations.declined')
                ->with('error', 'This invitation is no longer valid.');
        }
    } catch (\Exception $e) {
        return view('invitations.declined')
            ->with('error', 'There was an error processing your response.');
    }
})->name('invitations.decline');

// API endpoints for invitation management
Route::prefix('api/invitations')->middleware(['auth', 'verified'])->group(function () {
    // Get pending invitations for current user
    Route::get('/pending', function () {
        $invitationService = new App\Services\InvitationService();
        $invitations = $invitationService->getPendingInvitationsForUser(auth()->user());
        
        return response()->json([
            'data' => $invitations->map(function ($invitation) {
                return [
                    'id' => $invitation->id,
                    'company_name' => $invitation->company->name,
                    'role' => $invitation->role,
                    'inviter_name' => $invitation->inviter->name,
                    'expires_at' => $invitation->expires_at,
                    'created_at' => $invitation->created_at,
                ];
            }),
            'count' => $invitations->count(),
        ]);
    });
    
    // Accept invitation (API version)
    Route::post('/{invitation}/accept', function (App\Models\Invitation $invitation) {
        $invitationService = new App\Services\InvitationService();
        
        try {
            $success = $invitationService->acceptInvitation($invitation->token, auth()->user());
            
            if ($success) {
                return response()->json(['message' => 'Invitation accepted successfully']);
            } else {
                return response()->json(['message' => 'Invitation is no longer valid'], 422);
            }
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'message' => 'Validation failed',
                'errors' => $e->errors()
            ], 422);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Failed to accept invitation',
                'error' => $e->getMessage()
            ], 500);
        }
    });
    
    // Decline invitation (API version)
    Route::post('/{invitation}/decline', function (App\Models\Invitation $invitation) {
        $invitationService = new App\Services\InvitationService();
        
        try {
            $success = $invitationService->declineInvitation($invitation->token);
            
            if ($success) {
                return response()->json(['message' => 'Invitation declined successfully']);
            } else {
                return response()->json(['message' => 'Invitation is no longer valid'], 422);
            }
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Failed to decline invitation',
                'error' => $e->getMessage()
            ], 500);
        }
    });
});

// Company invitation management routes
Route::prefix('api/companies/{company}/invitations')->middleware(['auth', 'verified'])->group(function () {
    // Get company invitations
    Route::get('/', function (App\Models\Company $company) {
        $invitationService = new App\Services\InvitationService();
        $invitations = $invitationService->getPendingInvitationsForCompany($company);
        
        return response()->json([
            'data' => $invitations->map(function ($invitation) {
                return [
                    'id' => $invitation->id,
                    'email' => $invitation->email,
                    'role' => $invitation->role,
                    'inviter_name' => $invitation->inviter->name,
                    'status' => $invitation->status,
                    'expires_at' => $invitation->expires_at,
                    'created_at' => $invitation->created_at,
                ];
            }),
            'count' => $invitations->count(),
        ]);
    });
    
    // Cancel invitation
    Route::delete('/{invitation}', function (App\Models\Company $company, App\Models\Invitation $invitation) {
        $invitationService = new App\Services\InvitationService();
        
        try {
            $success = $invitationService->cancelInvitation($invitation->id, auth()->user());
            
            if ($success) {
                return response()->json(['message' => 'Invitation cancelled successfully']);
            } else {
                return response()->json(['message' => 'Invitation not found or already processed'], 404);
            }
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'message' => 'Permission denied',
                'errors' => $e->errors()
            ], 403);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Failed to cancel invitation',
                'error' => $e->getMessage()
            ], 500);
        }
    });
    
    // Resend invitation
    Route::post('/{invitation}/resend', function (App\Models\Company $company, App\Models\Invitation $invitation) {
        $invitationService = new App\Services\InvitationService();
        
        try {
            $success = $invitationService->resendInvitation($invitation->id, auth()->user());
            
            if ($success) {
                return response()->json(['message' => 'Invitation resent successfully']);
            } else {
                return response()->json(['message' => 'Invitation not found or no longer valid'], 404);
            }
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'message' => 'Permission denied',
                'errors' => $e->errors()
            ], 403);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Failed to resend invitation',
                'error' => $e->getMessage()
            ], 500);
        }
    });
});

require __DIR__.'/settings.php';
