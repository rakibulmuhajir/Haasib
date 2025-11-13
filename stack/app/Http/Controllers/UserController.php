<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class UserController extends Controller
{
    public function __construct() {}

    /**
     * Get all users in the system.
     */
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();
        
        // Only super admins can see all users
        if (!$user->isSuperAdmin()) {
            return response()->json([
                'message' => 'Unauthorized',
            ], 403);
        }

        $users = User::with(['companies' => function ($query) {
            $query->wherePivot('is_active', true);
        }])->get();

        return response()->json([
            'users' => $users->map(function ($user) {
                return [
                    'id' => $user->id,
                    'name' => $user->name,
                    'username' => $user->username,
                    'email' => $user->email,
                    'system_role' => $user->system_role,
                    'is_active' => $user->is_active,
                    'companies' => $user->companies->map(function ($company) {
                        return [
                            'id' => $company->id,
                            'name' => $company->name,
                            'role' => $company->pivot->role,
                        ];
                    }),
                ];
            }),
        ]);
    }

    /**
     * Get a specific user.
     */
    public function show(Request $request, string $id): JsonResponse
    {
        // User show command - not implemented
        return response()->json([
            'message' => 'User show command - not implemented',
        ], Response::HTTP_NOT_IMPLEMENTED);
    }

    /**
     * Create a new user.
     */
    public function create(Request $request): JsonResponse
    {
        // User create command - not implemented
        return response()->json([
            'message' => 'User create command - not implemented',
        ], Response::HTTP_NOT_IMPLEMENTED);
    }

    /**
     * Update a user.
     */
    public function update(Request $request, string $id): JsonResponse
    {
        // User update command - not implemented
        return response()->json([
            'message' => 'User update command - not implemented',
        ], Response::HTTP_NOT_IMPLEMENTED);
    }

    /**
     * Delete a user.
     */
    public function delete(Request $request, string $id): JsonResponse
    {
        // User delete command - not implemented
        return response()->json([
            'message' => 'User delete command - not implemented',
        ], Response::HTTP_NOT_IMPLEMENTED);
    }
}
