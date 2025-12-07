<?php

use App\Http\Controllers\CompaniesPageController;
use App\Http\Controllers\CompanyController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\InvitationController;
use App\Http\Controllers\UsersPageController;
use App\Modules\Accounting\Http\Controllers\CustomerController;
use App\Modules\Accounting\Http\Controllers\InvoiceController;
use App\Modules\Accounting\Http\Controllers\PaymentController;
use App\Modules\Accounting\Http\Controllers\CreditNoteController;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;
use Laravel\Fortify\Features;

Route::get('/', function () {
    return Inertia::render('Welcome', [
        'canRegister' => Features::enabled(Features::registration()),
    ]);
})->name('home');

Route::get('dashboard', [DashboardController::class, 'index'])
    ->middleware(['auth', 'verified'])
    ->name('dashboard');

// Invitation routes (public/guest access for viewing)
Route::get('/invite/{token}', [InvitationController::class, 'show'])->name('invitation.show');

Route::middleware(['auth'])->group(function () {
    // Invitation routes (authenticated)
    Route::post('/invite/{token}/accept', [InvitationController::class, 'accept'])->name('invitation.accept');
    Route::post('/invite/{token}/reject', [InvitationController::class, 'reject'])->name('invitation.reject');
    Route::get('/invitations/pending', [InvitationController::class, 'pending'])->name('invitations.pending');

    // Non-scoped company routes (creation and switching)
    Route::post('/companies', [CompanyController::class, 'store'])->name('companies.store');
    Route::post('/companies/switch', [CompaniesPageController::class, 'switch'])->name('companies.switch');
    Route::get('/companies', [CompaniesPageController::class, 'index'])->name('companies.index');
    Route::delete('/companies/{company}', [CompanyController::class, 'destroy'])->name('companies.destroy');

    // Company-scoped routes
    Route::middleware(['identify.company'])->group(function () {
        Route::get('/{company}', [CompanyController::class, 'show'])->name('company.show');
        Route::put('/{company}', [CompanyController::class, 'update'])->name('company.update');
        Route::get('/{company}/settings', [CompanyController::class, 'settings'])->name('company.settings');
        Route::get('/{company}/users', [UsersPageController::class, 'index'])->name('users.index');
        Route::post('/{company}/users/invite', [UsersPageController::class, 'invite'])->name('users.invite');
        Route::put('/{company}/users/{user}/role', [UsersPageController::class, 'updateRole'])->name('users.update-role');
        Route::delete('/{company}/users/{user}', [UsersPageController::class, 'remove'])->name('users.remove');
        Route::delete('/{company}/invitations/{invitation}', [InvitationController::class, 'revoke'])->name('invitations.revoke');

        // Customer routes (Accounting module)
        Route::get('/{company}/customers', [CustomerController::class, 'index'])->name('customers.index');
        Route::get('/{company}/customers/create', [CustomerController::class, 'create'])->name('customers.create');
        Route::post('/{company}/customers', [CustomerController::class, 'store'])->name('customers.store');
        Route::get('/{company}/customers/{customer}', [CustomerController::class, 'show'])->name('customers.show');
        Route::get('/{company}/customers/{customer}/edit', [CustomerController::class, 'edit'])->name('customers.edit');
        Route::put('/{company}/customers/{customer}', [CustomerController::class, 'update'])->name('customers.update');
        Route::delete('/{company}/customers/{customer}', [CustomerController::class, 'destroy'])->name('customers.destroy');

        // Invoice routes (Accounting module)
        Route::get('/{company}/invoices', [InvoiceController::class, 'index'])->name('invoices.index');
        Route::get('/{company}/invoices/create', [InvoiceController::class, 'create'])->name('invoices.create');
        Route::post('/{company}/invoices', [InvoiceController::class, 'store'])->name('invoices.store');
        Route::get('/{company}/invoices/{invoice}', [InvoiceController::class, 'show'])->name('invoices.show');
        Route::get('/{company}/invoices/{invoice}/edit', [InvoiceController::class, 'edit'])->name('invoices.edit');
        Route::put('/{company}/invoices/{invoice}', [InvoiceController::class, 'update'])->name('invoices.update');
        Route::delete('/{company}/invoices/{invoice}', [InvoiceController::class, 'destroy'])->name('invoices.destroy');

        // Payment routes (Accounting module)
        Route::get('/{company}/payments', [PaymentController::class, 'index'])->name('payments.index');
        Route::get('/{company}/payments/create', [PaymentController::class, 'create'])->name('payments.create');
        Route::post('/{company}/payments', [PaymentController::class, 'store'])->name('payments.store');
        Route::get('/{company}/payments/{payment}', [PaymentController::class, 'show'])->name('payments.show');
        Route::get('/{company}/payments/{payment}/edit', [PaymentController::class, 'edit'])->name('payments.edit');
        Route::put('/{company}/payments/{payment}', [PaymentController::class, 'update'])->name('payments.update');
        Route::delete('/{company}/payments/{payment}', [PaymentController::class, 'destroy'])->name('payments.destroy');

        // Credit Note routes (Accounting module)
        Route::get('/{company}/credit-notes', [CreditNoteController::class, 'index'])->name('credit-notes.index');
        Route::get('/{company}/credit-notes/create', [CreditNoteController::class, 'create'])->name('credit-notes.create');
        Route::post('/{company}/credit-notes', [CreditNoteController::class, 'store'])->name('credit-notes.store');
        Route::get('/{company}/credit-notes/{credit_note}', [CreditNoteController::class, 'show'])->name('credit-notes.show');
        Route::get('/{company}/credit-notes/{credit_note}/edit', [CreditNoteController::class, 'edit'])->name('credit-notes.edit');
        Route::put('/{company}/credit-notes/{credit_note}', [CreditNoteController::class, 'update'])->name('credit-notes.update');
        Route::delete('/{company}/credit-notes/{credit_note}', [CreditNoteController::class, 'destroy'])->name('credit-notes.destroy');
    });
});

require __DIR__.'/settings.php';
