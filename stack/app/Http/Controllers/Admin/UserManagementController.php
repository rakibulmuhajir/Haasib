<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\CreateUserRequest;
use App\Http\Requests\Admin\ResetPasswordRequest;
use App\Http\Requests\Admin\UpdateUserRequest;
use App\Models\Company;
use App\Models\User;
use App\Services\ServiceContextHelper;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class UserManagementController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth');
        $this->middleware(['permission:admin.access']);
        $this->middleware(['throttle:30,1'])->only(['store', 'update', 'destroy', 'resetPassword']);
    }

    /**
     * Display all users with their company associations.
     */
    public function index(): Response
    {
        try {
            $admin = request()->user();

            $search = request()->get('search', '');
            $role = request()->get('role', '');
            $isActive = request()->get('is_active');

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
                'can' => [
                    'create' => $admin->can('admin.users.create'),
                    'update' => $admin->can('admin.users.update'),
                    'delete' => $admin->can('admin.users.delete'),
                    'reset_password' => $admin->can('admin.users.reset_password'),
                ],
            ]);

        } catch (\Exception $e) {
            Log::error('User management listing failed', [
                'error' => $e->getMessage(),
                'admin_id' => request()->user()->id,
                'company_id' => request()->user()?->currentCompany()?->id,
            ]);

            return Inertia::render('Admin/Users/Index', [
                'users' => collect(),
                'filters' => [
                    'search' => '',
                    'role' => '',
                    'is_active' => '',
                ],
                'roles' => ['super_admin', 'admin', 'user', 'guest'],
                'error' => 'Failed to load users',
            ]);
        }
    }

    /**
     * Show the form for creating a new user.
     */
    public function create(): Response
    {
        try {
            $admin = request()->user();

            return Inertia::render('Admin/Users/Create', [
                'companies' => Company::orderBy('name')->get(),
                'roles' => ['super_admin', 'admin', 'user', 'guest'],
                'companyRoles' => ['owner', 'admin', 'member', 'viewer'],
                'can' => [
                    'create' => $admin->can('admin.users.create'),
                ],
            ]);

        } catch (\Exception $e) {
            Log::error('User creation form failed', [
                'error' => $e->getMessage(),
                'admin_id' => request()->user()->id,
            ]);

            return Inertia::render('Admin/Users/Create', [
                'companies' => collect(),
                'roles' => ['super_admin', 'admin', 'user', 'guest'],
                'error' => 'Failed to load creation form',
            ]);
        }
    }

    /**
     * Store a newly created user.
     */
    public function store(CreateUserRequest $request): JsonResponse
    {
        try {
            $context = ServiceContextHelper::fromRequest($request);

            $user = Bus::dispatch('users.create', [
                'name' => $request->validated('name'),
                'email' => $request->validated('email'),
                'username' => $request->validated('username'),
                'password' => $request->validated('password'),
                'system_role' => $request->validated('system_role'),
                'is_active' => $request->validated('is_active'),
                'companies' => $request->validated('companies', []),
            ], $context);

            return response()->json([
                'success' => true,
                'data' => [
                    'user' => $user,
                ],
                'message' => 'User created successfully',
            ], 201);

        } catch (ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $e->errors(),
            ], 422);

        } catch (\Exception $e) {
            Log::error('User creation failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'admin_id' => request()->user()->id,
                'company_id' => request()->user()?->currentCompany()?->id,
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to create user',
                'errors' => config('app.debug') ? [$e->getMessage()] : [],
            ], 500);
        }
    }

    /**
     * Display the specified user.
     */
    public function show(User $user): Response
    {
        try {
            $admin = request()->user();

            $user->load(['companies' => function ($query) {
                $query->withPivot('role', 'is_active', 'created_by');
            }]);

            return Inertia::render('Admin/Users/Show', [
                'user' => $user,
                'can' => [
                    'update' => $admin->can('admin.users.update') && $this->canManageUser($admin, $user),
                    'delete' => $admin->can('admin.users.delete') && $this->canManageUser($admin, $user),
                    'reset_password' => $admin->can('admin.users.reset_password') && $this->canManageUser($admin, $user),
                ],
            ]);

        } catch (\Exception $e) {
            Log::error('User details display failed', [
                'error' => $e->getMessage(),
                'user_id' => $user->id,
                'admin_id' => request()->user()->id,
            ]);

            return redirect()->route('admin.users.index')
                ->with('error', 'Failed to load user details');
        }
    }

    /**
     * Show the form for editing the specified user.
     */
    public function edit(UpdateUserRequest $request, User $user): Response
    {
        try {
            $context = ServiceContextHelper::fromRequest($request);
            $admin = $context->getUser();

            $user->load(['companies' => function ($query) {
                $query->withPivot('role', 'is_active');
            }]);

            return Inertia::render('Admin/Users/Edit', [
                'user' => $user,
                'companies' => Company::orderBy('name')->get(),
                'roles' => ['super_admin', 'admin', 'user', 'guest'],
                'companyRoles' => ['owner', 'admin', 'member', 'viewer'],
                'can' => [
                    'update' => $admin->can('admin.users.update') && $this->canManageUser($admin, $user),
                    'reset_password' => $admin->can('admin.users.reset_password') && $this->canManageUser($admin, $user),
                ],
            ]);

        } catch (\Exception $e) {
            Log::error('User edit form failed', [
                'error' => $e->getMessage(),
                'user_id' => $user->id,
                'admin_id' => request()->user()->id,
            ]);

            return redirect()->route('admin.users.show', $user->id)
                ->with('error', 'Failed to load edit form');
        }
    }

    /**
     * Update the specified user in storage.
     */
    public function update(UpdateUserRequest $request, User $user): JsonResponse
    {
        try {
            $context = ServiceContextHelper::fromRequest($request);

            $updatedUser = Bus::dispatch('users.update', [
                'id' => $user->id,
                'name' => $request->validated('name'),
                'email' => $request->validated('email'),
                'username' => $request->validated('username'),
                'system_role' => $request->validated('system_role'),
                'is_active' => $request->validated('is_active'),
                'password' => $request->validated('password'), // May be null
                'companies' => $request->validated('companies', []),
            ], $context);

            return response()->json([
                'success' => true,
                'data' => [
                    'user' => $updatedUser,
                ],
                'message' => 'User updated successfully',
            ]);

        } catch (ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $e->errors(),
            ], 422);

        } catch (\Exception $e) {
            Log::error('User update failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'user_id' => $user->id,
                'admin_id' => request()->user()->id,
                'company_id' => request()->user()?->currentCompany()?->id,
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to update user',
                'errors' => config('app.debug') ? [$e->getMessage()] : [],
            ], 500);
        }
    }

    /**
     * Remove the specified user from storage.
     */
    public function destroy(User $user): JsonResponse
    {
        try {
            $admin = request()->user();

            // Additional validation
            if ($user->id === $admin->id) {
                return response()->json([
                    'success' => false,
                    'message' => 'You cannot delete your own account',
                    'errors' => ['self_deletion' => ['Self-deletion is not allowed']],
                ], 403);
            }

            if (! $this->canManageUser($admin, $user)) {
                return response()->json([
                    'success' => false,
                    'message' => 'You do not have permission to delete this user',
                    'errors' => ['permission' => ['Insufficient permissions']],
                ], 403);
            }

            // Delete user with cascade
            $user->delete();

            Log::security('ADMIN_USER_DELETED', [
                'admin_id' => $admin->id,
                'admin_name' => $admin->name,
                'deleted_user_id' => $user->id,
                'deleted_user_name' => $user->name,
                'deleted_user_email' => $user->email,
                'ip' => request()->ip(),
                'user_agent' => request()->userAgent(),
            ]);

            return response()->json([
                'success' => true,
                'message' => 'User deleted successfully',
            ]);

        } catch (\Exception $e) {
            Log::error('User deletion failed', [
                'error' => $e->getMessage(),
                'user_id' => $user->id,
                'admin_id' => $request->user()->id,
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to delete user',
                'errors' => config('app.debug') ? [$e->getMessage()] : [],
            ], 500);
        }
    }

    /**
     * Toggle user active status.
     */
    public function toggleStatus(User $user): JsonResponse
    {
        try {
            $admin = request()->user();

            // Additional validation
            if ($user->id === $admin->id) {
                return response()->json([
                    'success' => false,
                    'message' => 'You cannot deactivate your own account',
                    'errors' => ['self_deactivation' => ['Self-deactivation is not allowed']],
                ], 403);
            }

            if (! $this->canManageUser($admin, $user)) {
                return response()->json([
                    'success' => false,
                    'message' => 'You do not have permission to modify this user',
                    'errors' => ['permission' => ['Insufficient permissions']],
                ], 403);
            }

            $newStatus = ! $user->is_active;
            $user->update(['is_active' => $newStatus]);

            Log::security('ADMIN_USER_STATUS_TOGGLED', [
                'admin_id' => $admin->id,
                'admin_name' => $admin->name,
                'user_id' => $user->id,
                'user_name' => $user->name,
                'user_email' => $user->email,
                'old_status' => ! $newStatus,
                'new_status' => $newStatus,
                'ip' => request()->ip(),
                'user_agent' => request()->userAgent(),
            ]);

            return response()->json([
                'success' => true,
                'data' => [
                    'user' => $user,
                ],
                'message' => 'User status updated successfully',
            ]);

        } catch (\Exception $e) {
            Log::error('User status toggle failed', [
                'error' => $e->getMessage(),
                'user_id' => $user->id,
                'admin_id' => request()->user()->id,
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to update user status',
                'errors' => config('app.debug') ? [$e->getMessage()] : [],
            ], 500);
        }
    }

    /**
     * Reset user password.
     */
    public function resetPassword(ResetPasswordRequest $request, User $user): JsonResponse
    {
        try {
            $context = ServiceContextHelper::fromRequest($request);

            $result = Bus::dispatch('users.reset_password', [
                'id' => $user->id,
                'password' => $request->validated('password'),
                'send_email' => $request->boolean('send_email', true),
                'force_change_on_login' => $request->boolean('force_change_on_login', false),
                'reason' => $request->validated('reason', 'Password reset by administrator'),
            ], $context);

            return response()->json([
                'success' => true,
                'data' => $result,
                'message' => 'Password reset successfully',
            ]);

        } catch (ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $e->errors(),
            ], 422);

        } catch (\Exception $e) {
            Log::error('User password reset failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'user_id' => $user->id,
                'admin_id' => request()->user()->id,
                'company_id' => request()->user()?->currentCompany()?->id,
            ]);

            Log::security('ADMIN_PASSWORD_RESET_FAILED', [
                'admin_id' => request()->user()->id,
                'target_user_id' => $user->id,
                'error' => $e->getMessage(),
                'ip' => request()->ip(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to reset password',
                'errors' => config('app.debug') ? [$e->getMessage()] : [],
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

            return response()->json([
                'success' => true,
                'data' => $stats,
                'message' => 'User statistics retrieved successfully',
            ]);

        } catch (\Exception $e) {
            Log::error('User statistics retrieval failed', [
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to get user statistics',
                'errors' => config('app.debug') ? [$e->getMessage()] : [],
            ], 500);
        }
    }

    /**
     * Check if current admin can manage the specified user
     */
    private function canManageUser(User $admin, User $user): bool
    {
        // Super admin can manage anyone
        if ($admin->hasRole('super_admin')) {
            return true;
        }

        // Admin can manage users and guests
        if ($admin->hasRole('admin')) {
            return in_array($user->system_role, ['user', 'guest']);
        }

        return false;
    }
}
