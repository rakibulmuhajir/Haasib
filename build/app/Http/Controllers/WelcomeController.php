<?php

namespace App\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;

class WelcomeController extends Controller
{
    /**
     * Show welcome page for first-time users.
     */
    public function index(): RedirectResponse
    {
        if (Auth::user()?->isGodMode()) {
            return redirect()->route('companies.create');
        }

        return redirect()->route('companies.index')
            ->with('error', 'Ask a super admin to create a company for you or invite you to an existing company.');
    }
}
