<?php

namespace App\Http\Controllers;

use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Inertia\Response;

class WelcomeController extends Controller
{
    /**
     * Show welcome page for first-time users.
     */
    public function index(): Response
    {
        $user = Auth::user();

        return Inertia::render('onboarding/FirstTimeSetup', [
            'user' => [
                'name' => $user->name,
                'email' => $user->email,
            ],
        ]);
    }
}
