<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Auth\Events\Registered;
use Illuminate\Database\QueryException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules;
use Inertia\Inertia;
use Inertia\Response;

class RegisteredUserController extends Controller
{
    /**
     * Display the registration view.
     */
    public function create(): Response
    {
        return Inertia::render('Auth/Register');
    }

    /**
     * Handle an incoming registration request.
     *
     * @throws \Illuminate\Validation\ValidationException
     */
    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'username' => [
                'required',
                'string',
                'alpha_dash',
                'min:3',
                'max:255',
                Rule::unique('auth.users', 'username'),
            ],
            'email' => [
                'required',
                'string',
                'lowercase',
                'email',
                'max:255',
                Rule::unique('auth.users', 'email'),
            ],
            'password' => ['required', 'confirmed', Rules\Password::defaults()],
        ]);

        try {
            DB::beginTransaction();

            $user = User::create([
                'name' => $validated['name'],
                'username' => $validated['username'],
                'email' => $validated['email'],
                'password' => Hash::make($validated['password']),
                'system_role' => 'user',
                'is_active' => true,
            ]);

            event(new Registered($user));

            DB::commit();

            Auth::login($user);
        } catch (QueryException $exception) {
            DB::rollBack();

            Log::error('Registration failed due to database constraint', [
                'code' => $exception->getCode(),
                'message' => $exception->getMessage(),
                'email' => $validated['email'],
                'username' => $validated['username'],
            ]);

            $message = 'We could not create your account. Please verify your details and try again.';

            if ((string) $exception->getCode() === '23505') {
                $message = 'That username or email is already registered.';
            }

            return back()
                ->withErrors(['general' => $message])
                ->withInput();
        } catch (\Throwable $exception) {
            DB::rollBack();

            Log::error('Registration failed unexpectedly', [
                'error' => $exception->getMessage(),
            ]);

            return back()
                ->withErrors(['general' => 'Something went wrong while creating your account. Please try again shortly.'])
                ->withInput();
        }

        return redirect(route('dashboard', absolute: false))
            ->with('success', 'Welcome aboard! Your account is ready.');
    }
}
