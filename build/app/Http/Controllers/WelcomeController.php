<?php

namespace App\Http\Controllers;

use Illuminate\Http\RedirectResponse;

class WelcomeController extends Controller
{
    /**
     * Show welcome page for first-time users.
     */
    public function index(): RedirectResponse
    {
        return redirect()->route('companies.create');
    }
}
