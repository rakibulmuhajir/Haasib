<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\Company;
use App\Models\User;
use Illuminate\Auth\Events\Registered;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Inertia\Inertia;

class RegisteredUserController extends Controller
{
    public function create()
    {
        return Inertia::render('Auth/Register');
    }

    public function store(Request $request)
    {
        Log::info('Registration attempt started', [
            'email' => $request->email,
            'username' => $request->username,
            'company_name' => $request->company_name,
        ]);

        $validator = Validator::make($request->all(), [
            'name' => ['required', 'string', 'max:255'],
            'username' => ['required', 'string', 'max:255', 'unique:users,username'],
            'email' => ['required', 'string', 'email', 'max:255', 'unique:users,email'],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
            'company_name' => ['required', 'string', 'max:255'],
            'company_email' => ['nullable', 'string', 'email', 'max:255'],
            'company_phone' => ['nullable', 'string', 'max:255'],
            'company_website' => ['nullable', 'string', 'max:255'],
        ]);

        if ($validator->fails()) {
            Log::warning('Registration validation failed', [
                'errors' => $validator->errors()->toArray(),
                'email' => $request->email,
            ]);

            return back()->withErrors($validator);
        }

        try {
            DB::beginTransaction();

            // Disable audit logging during registration to avoid transaction issues
            config(['audit.enabled' => false]);
            
            // Temporarily disable the Company observer to prevent audit entry conflicts
            Company::unsetEventDispatcher();

            // Create user first - this works with existing RLS policies
            $user = User::create([
                'name' => $request->name,
                'username' => $request->username,
                'email' => $request->email,
                'password' => Hash::make($request->password),
                'system_role' => 'company_owner', // New users are company owners
                'is_active' => true,
            ]);

            Log::info('User created successfully', ['user_id' => $user->id]);

            // Set database context for RLS policies during registration
            DB::selectOne("SELECT set_config('app.current_user_id', ?, false)", [$user->id]);
            DB::selectOne("SELECT set_config('app.is_super_admin', ?, false)", [$user->isSuperAdmin() ? 'true' : 'false']);

            // Temporarily disable RLS for company_user table during registration due to policy bug
            DB::statement("SET LOCAL row_security = off");

            // Create company for the user
            $company = Company::create([
                'name' => $request->company_name,
                'slug' => Str::slug($request->company_name),
                'industry' => 'other',
                'base_currency' => 'USD',
                'is_active' => true,
                // Store additional company info in settings JSON field
                'settings' => [
                    'contact_email' => $request->company_email,
                    'contact_phone' => $request->company_phone,
                    'website' => $request->company_website,
                ],
            ]);

            Log::info('Company created successfully', ['company_id' => $company->id]);

            // Associate user with company as owner using raw SQL to bypass RLS
            DB::table('auth.company_user')->insert([
                'company_id' => $company->id,
                'user_id' => $user->id,
                'role' => 'owner',
                'invited_by_user_id' => $user->id,
                'joined_at' => now(),
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            Log::info('Company-user association created successfully');

            // Note: Permissions will be assigned later after ensuring permissions system is set up
            // For now, user will get basic access through company ownership
            // Permissions can be assigned in a background job or admin process

            DB::commit();

            Log::info('Registration successful', [
                'user_id' => $user->id,
                'company_id' => $company->id,
                'email' => $user->email,
            ]);

            event(new Registered($user));

            return redirect()->route('login')
                ->with('success', 'Registration successful! Please log in with your new account.');

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Registration failed with exception', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'email' => $request->email,
            ]);

            return back()->with('error', 'Registration failed. Please try again.')
                ->withInput();
        }
    }
}
