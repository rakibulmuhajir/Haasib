<?php

namespace App\Http\Controllers;

use App\Facades\CompanyContext;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class UsersPageController extends Controller
{
    public function index(Request $request): Response
    {
        $company = CompanyContext::getCompany();

        $users = DB::table('auth.company_user as cu')
            ->join('auth.users as u', 'cu.user_id', '=', 'u.id')
            ->where('cu.company_id', $company->id)
            ->select(
                'u.id',
                'u.name',
                'u.email',
                'cu.role',
                'cu.is_active',
                'cu.joined_at'
            )
            ->orderBy('u.name')
            ->get();

        return Inertia::render('users/Index', [
            'company' => [
                'id' => $company->id,
                'name' => $company->name,
                'slug' => $company->slug,
            ],
            'users' => $users,
        ]);
    }

    public function invite(Request $request): JsonResponse
    {
        $data = $request->validate([
            'email' => ['required', 'email'],
            'role' => ['required', 'string', 'in:owner,admin,manager,user'],
        ]);

        $company = CompanyContext::getCompany();
        $data['company_id'] = $company->id;
        $data['invited_by_user_id'] = Auth::id();

        $result = Bus::dispatch('user.invite', $data);

        return response()->json([
            'success' => true,
            'message' => $result['message'] ?? 'Invitation sent successfully.',
        ]);
    }

    public function updateRole(Request $request, string $userId): JsonResponse
    {
        $data = $request->validate([
            'role' => ['required', 'string', 'in:owner,admin,manager,user'],
        ]);

        $company = CompanyContext::getCompany();

        $result = Bus::dispatch('user.update-role', [
            'company_id' => $company->id,
            'user_id' => $userId,
            'role' => $data['role'],
        ]);

        return response()->json([
            'success' => true,
            'message' => $result['message'] ?? 'User role updated successfully.',
        ]);
    }

    public function remove(Request $request, string $userId): JsonResponse
    {
        $company = CompanyContext::getCompany();

        // Check if current user is owner or admin
        $currentUserRole = DB::table('auth.company_user')
            ->where('company_id', $company->id)
            ->where('user_id', Auth::id())
            ->value('role');

        if (!in_array($currentUserRole, ['owner', 'admin'])) {
            abort(403, 'Only owners and admins can remove users.');
        }

        // Don't allow removing yourself
        if ($userId === Auth::id()) {
            abort(403, 'You cannot remove yourself from the company.');
        }

        DB::table('auth.company_user')
            ->where('company_id', $company->id)
            ->where('user_id', $userId)
            ->delete();

        return response()->json([
            'success' => true,
            'message' => 'User removed from company successfully.',
        ]);
    }
}
