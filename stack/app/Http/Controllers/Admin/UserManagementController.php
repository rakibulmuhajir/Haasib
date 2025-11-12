<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Company;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class UserManagementController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth');
        $this->middleware(['permission:admin.access']);
    }

    /**
     * Display all users with their company associations.
     */
    public function index(Request $request): Response
    {
        $search = $request->get('search', '');
        $role = $request->get('role', '');
        $isActive = $request->get('is_active');

        $usersQuery = User::with(['companies' => function ($query) {
            $query->wherePivot('is_active', true);
        }]);

        // Apply filters
        if ($search) {
            $usersQuery->where(function ($query) use ($search) {
                $query->where('name', 'ILIKE', "%{$search}%")
                    ->orWhere('email', 'ILIKE', "%{$search}%")
                    ->orWhere('username', 'ILIKE', "%{$search}%");
            });
        }

        if ($role) {
            $usersQuery->where('system_role', $role);
        }

        if ($isActive !== '') {
            $usersQuery->where('is_active', $isActive === 'true');
        }

        $users = $usersQuery->orderBy('created_at', 'desc')->paginate(15);

        return Inertia::render('Admin/Users/Index', [
            'users' => $users,
            'filters' => [
                'search' => $search,
                'role' => $role,
                'is_active' => $isActive,
            ],
            'roles' => ['super_admin', 'admin', 'user', 'guest'],
        ]);
    }

    /**
     * Show the form for creating a new user.
     */
    public function create(): Response
    {
        return Inertia::render('Admin/Users/Create', [
            'companies' => Company::orderBy('name')->get(),
            'roles' => ['super_admin', 'admin', 'user', 'guest'],
        ]);
    }

    /**
     * Store a newly created user.
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', 'unique:users'],
            'username' => ['required', 'string', 'max:255', 'unique:users'],
            'password' => ['required', 'string', 'min:8'],
            'system_role' => ['required', 'string', Rule::in(['super_admin', 'admin', 'user', 'guest'])],
            'is_active' => ['boolean', 'default: true'],
            'companies' => ['array', 'present'],
            'companies.*.company_id' => ['required', 'exists:companies,id'],
            'companies.*.role' => ['required', 'string', Rule::in(['owner', 'admin', 'member', 'viewer'])],
        ]);

        try {
            DB::beginTransaction();

            // Create the user
            $user = User::create([
                'name' => $validated['name'],
                'email' => $validated['email'],
                'username' => $validated['username'],
                'password' => Hash::make($validated['password']),
                'system_role' => $validated['system_role'],
                'is_active' => $validated['is_active'],
            ]);

            // Attach companies if provided
            if (! empty($validated['companies'])) {
                foreach ($validated['companies'] as $companyData) {
                    $user->companies()->attach($companyData['company_id'], [
                        'role' => $companyData['role'],
                        'is_active' => true,
                        'created_by' => $request->user()->id,
                    ]);
                }
            }

            DB::commit();

            Log::info('User created', [
                'user_id' => $user->id,
                'created_by' => $request->user()->id,
                'system_role' => $user->system_role,
            ]);

            return response()->json([
                'message' => 'User created successfully',
                'user' => $user->load('companies'),
            ], 201);

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Failed to create user', [
                'error' => $e->getMessage(),
                'validated' => $validated,
            ]);

            return response()->json([
                'message' => 'Failed to create user: '.$e->getMessage(),
            ], 500);
        }
    }

    /**
     * Display the specified user.
     */
    public function show(User $user): Response
    {
        $user->load(['companies' => function ($query) {
            $query->withPivot('role', 'is_active', 'created_by');
        }]);

        return Inertia::render('Admin/Users/Show', [
            'user' => $user,
        ]);
    }

    /**
     * Show the form for editing the specified user.
     */
    public function edit(User $user): Response
    {
        $user->load(['companies' => function ($query) {
            $query->withPivot('role', 'is_active');
        }]);

        return Inertia::render('Admin/Users/Edit', [
            'user' => $user,
            'companies' => Company::orderBy('name')->get(),
            'roles' => ['super_admin', 'admin', 'user', 'guest'],
            'companyRoles' => ['owner', 'admin', 'member', 'viewer'],
        ]);
    }

    /**
     * Update the specified user in storage.
     */
    public function update(Request $request, User $user): JsonResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', Rule::unique('users')->ignore($user->id)],
            'username' => ['required', 'string', 'max:255', Rule::unique('users')->ignore($user->id)],
            'system_role' => ['required', 'string', Rule::in(['super_admin', 'admin', 'user', 'guest'])],
            'is_active' => ['boolean'],
            'companies' => ['array', 'present'],
            'companies.*.company_id' => ['required', 'exists:companies,id'],
            'companies.*.role' => ['required', 'string', Rule::in(['owner', 'admin', 'member', 'viewer'])],
        ]);

        try {
            DB::beginTransaction();

            // Update user details
            $user->update([
                'name' => $validated['name'],
                'email' => $validated['email'],
                'username' => $validated['username'],
                'system_role' => $validated['system_role'],
                'is_active' => $validated['is_active'],
            ]);

            // Update password if provided
            if (! empty($validated['password'])) {
                $user->update([
                    'password' => Hash::make($validated['password']),
                ]);
            }

            // Sync company associations
            if (! empty($validated['companies'])) {
                $user->companies()->detach(); // Remove all existing associations

                foreach ($validated['companies'] as $companyData) {
                    $user->companies()->attach($companyData['company_id'], [
                        'role' => $companyData['role'],
                        'is_active' => true,
                        'updated_by' => $request->user()->id,
                    ]);
                }
            } else {
                $user->companies()->detach(); // Remove all if empty
            }

            DB::commit();

            Log::info('User updated', [
                'user_id' => $user->id,
                'updated_by' => $request->user()->id,
                'changes' => array_keys($validated),
            ]);

            return response()->json([
                'message' => 'User updated successfully',
                'user' => $user->load('companies'),
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Failed to update user', [
                'error' => $e->getMessage(),
                'user_id' => $user->id,
                'validated' => $validated,
            ]);

            return response()->json([
                'message' => 'Failed to update user: '.$e->getMessage(),
            ], 500);
        }
    }

    /**
     * Remove the specified user from storage.
     */
    public function destroy(User $user): JsonResponse
    {
        if ($user->id === $request->user()->id) {
            return response()->json([
                'message' => 'You cannot delete your own account',
            ], 403);
        }

        try {
            DB::beginTransaction();

            // Detach from all companies
            $user->companies()->detach();

            // Delete the user
            $user->delete();

            DB::commit();

            Log::info('User deleted', [
                'user_id' => $user->id,
                'deleted_by' => $request->user()->id,
            ]);

            return response()->json([
                'message' => 'User deleted successfully',
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Failed to delete user', [
                'error' => $e->getMessage(),
                'user_id' => $user->id,
            ]);

            return response()->json([
                'message' => 'Failed to delete user: '.$e->getMessage(),
            ], 500);
        }
    }

    /**
     * Toggle user active status.
     */
    public function toggleStatus(Request $request, User $user): JsonResponse
    {
        if ($user->id === $request->user()->id) {
            return response()->json([
                'message' => 'You cannot deactivate your own account',
            ], 403);
        }

        try {
            $user->update([
                'is_active' => ! $user->is_active,
            ]);

            Log::info('User status toggled', [
                'user_id' => $user->id,
                'is_active' => $user->is_active,
                'toggled_by' => $request->user()->id,
            ]);

            return response()->json([
                'message' => 'User status updated successfully',
                'user' => $user,
            ]);

        } catch (\Exception $e) {
            Log::error('Failed to toggle user status', [
                'error' => $e->getMessage(),
                'user_id' => $user->id,
            ]);

            return response()->json([
                'message' => 'Failed to update user status: '.$e->getMessage(),
            ], 500);
        }
    }

    /**
     * Reset user password.
     */
    public function resetPassword(Request $request, User $user): JsonResponse
    {
        $validated = $request->validate([
            'password' => ['required', 'string', 'min:8'],
        ]);

        try {
            $user->update([
                'password' => Hash::make($validated['password']),
                'password_changed_at' => now(),
            ]);

            Log::info('Password reset', [
                'user_id' => $user->id,
                'reset_by' => $request->user()->id,
            ]);

            return response()->json([
                'message' => 'Password reset successfully',
            ]);

        } catch (\Exception $e) {
            Log::error('Failed to reset password', [
                'error' => $e->getMessage(),
                'user_id' => $user->id,
            ]);

            return response()->json([
                'message' => 'Failed to reset password: '.$e->getMessage(),
            ], 500);
        }
    }

    /**
     * Get user statistics for admin dashboard.
     */
    public function statistics(): JsonResponse
    {
        try {
            $stats = [
                'total_users' => User::count(),
                'active_users' => User::where('is_active', true)->count(),
                'inactive_users' => User::where('is_active', false)->count(),
                'users_by_role' => User::selectRaw('system_role, COUNT(*) as count')
                    ->groupBy('system_role')
                    ->pluck('count', 'system_role')
                    ->toArray(),
                'recent_registrations' => User::where('created_at', '>=', now()->subDays(30))
                    ->count(),
                'users_created_this_month' => User::whereMonth('created_at', now()->month)
                    ->whereYear('created_at', now()->year)
                    ->count(),
            ];

            return response()->json($stats);

        } catch (\Exception $e) {
            Log::error('Failed to get user statistics', [
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'message' => 'Failed to get user statistics',
            ], 500);
        }
    }
}
