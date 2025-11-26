<?php

use App\Http\Controllers\CompanyController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\InvitationController;
use App\Http\Controllers\InvoiceController;
use App\Http\Controllers\MemberController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
*/

// Public routes
Route::get('/', function () {
    return inertia('Welcome');
});

// Invitation acceptance (works for both guests and logged-in users)
Route::get('/invite/{token}', [InvitationController::class, 'show'])->name('invitations.show');
Route::post('/invite/{token}/accept', [InvitationController::class, 'accept'])->name('invitations.accept');

// Auth routes (Breeze/Jetstream/Fortify handles these)
require __DIR__.'/auth.php';

/*
|--------------------------------------------------------------------------
| Authenticated Routes (No company context)
|--------------------------------------------------------------------------
*/
Route::middleware(['auth', 'verified'])->group(function () {

    // Company selection / creation
    Route::get('/companies', [CompanyController::class, 'index'])->name('companies.index');
    Route::get('/companies/create', [CompanyController::class, 'create'])->name('companies.create');
    Route::post('/companies', [CompanyController::class, 'store'])->name('companies.store');

    // Redirect to default company
    Route::get('/dashboard', function () {
        $company = auth()->user()->defaultCompany();

        if (!$company) {
            return redirect()->route('companies.create');
        }

        return redirect()->route('company.dashboard', $company);
    })->name('dashboard');
});

/*
|--------------------------------------------------------------------------
| Company-Scoped Routes
|--------------------------------------------------------------------------
*/
Route::middleware(['auth', 'verified', 'identify.company'])
    ->prefix('{company}')
    ->name('company.')
    ->group(function () {

        // Dashboard
        Route::get('/', [DashboardController::class, 'index'])->name('dashboard');

        // Company settings (owner only typically)
        Route::get('/settings', [CompanyController::class, 'settings'])
            ->name('settings')
            ->can('viewSettings', 'company');

        Route::put('/settings', [CompanyController::class, 'updateSettings'])
            ->name('settings.update')
            ->can('updateSettings', 'company');

        // Members management
        Route::prefix('members')->name('members.')->group(function () {
            Route::get('/', [MemberController::class, 'index'])
                ->name('index')
                ->can('viewMembers', 'company');

            Route::post('/invite', [MemberController::class, 'invite'])
                ->name('invite')
                ->can('inviteMembers', 'company');

            Route::put('/{user}/role', [MemberController::class, 'updateRole'])
                ->name('update-role')
                ->can('updateMemberRole', 'company');

            Route::delete('/{user}', [MemberController::class, 'remove'])
                ->name('remove')
                ->can('removeMembers', 'company');
        });

        // Invitations
        Route::prefix('invitations')->name('invitations.')->group(function () {
            Route::get('/', [InvitationController::class, 'index'])
                ->name('index')
                ->can('inviteMembers', 'company');

            Route::delete('/{invitation}', [InvitationController::class, 'revoke'])
                ->name('revoke')
                ->can('inviteMembers', 'company');

            Route::post('/{invitation}/resend', [InvitationController::class, 'resend'])
                ->name('resend')
                ->can('inviteMembers', 'company');
        });

        /*
        |--------------------------------------------------------------------------
        | Accounts Module Routes
        |--------------------------------------------------------------------------
        */

        // Invoices
        Route::resource('invoices', InvoiceController::class);

        // Custom invoice actions
        Route::post('/invoices/{invoice}/approve', [InvoiceController::class, 'approve'])
            ->name('invoices.approve');

        Route::post('/invoices/{invoice}/void', [InvoiceController::class, 'void'])
            ->name('invoices.void');

        Route::post('/invoices/{invoice}/send', [InvoiceController::class, 'send'])
            ->name('invoices.send');

        // Add more module routes here...
        // Route::resource('accounts', AccountController::class);
        // Route::resource('journals', JournalController::class);
    });
