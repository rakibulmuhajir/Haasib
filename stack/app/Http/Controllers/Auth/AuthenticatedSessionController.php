<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Inertia\Inertia;

class AuthenticatedSessionController extends Controller
{
    public function create()
    {
        return inertia()->render('Auth/Login');
    }

    public function store(Request $request)
    {
        $credentials = $request->validate([
            'username' => 'required|string',
            'password' => 'required|string',
        ]);

        $authService = app(\App\Services\AuthService::class);
        $result = $authService->login($credentials['username'], $credentials['password']);

        if (!$result['success']) {
            return back()->withErrors([
                'username' => 'Invalid credentials'
            ])->withInput();
        }

        $request->session()->regenerate();
        
        return redirect()->intended('/dashboard');
    }

    public function destroy(Request $request)
    {
        auth()->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();
        
        return redirect('/');
    }
}
