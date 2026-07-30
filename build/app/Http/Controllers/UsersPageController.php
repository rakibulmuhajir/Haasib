<?php

namespace App\Http\Controllers;

use App\Facades\CompanyContext;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class UsersPageController extends Controller
{
    public function index(Request $request): Response
    {
        $company = CompanyContext::getCompany();

        // Get current user's role first
        $currentUserRole = DB::table('auth.company_user')
            ->where('company_id', $company->id)
            ->where('user_id', Auth::id())
            ->value('role');

        // If user is not a member, they shouldn't be here
        if (! $currentUserRole) {
            abort(403, 'You are not a member of this company.');
        }

        $users = DB::table('auth.company_user as cu')
            ->join('auth.users as u', 'cu.user_id', '=', 'u.id')
            ->where('cu.company_id', $company->id)
            ->where('cu.is_active', true)
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

        // Get pending invitations for this company
        $pendingInvitations = DB::table('auth.company_invitations as ci')
            ->leftJoin('auth.users as inviter', 'ci.invited_by_user_id', '=', 'inviter.id')
            ->where('ci.company_id', $company->id)
            ->where('ci.status', 'pending')
            ->where('ci.expires_at', '>', now())
            ->select(
                'ci.id',
                'ci.email',
                'ci.role',
                'ci.token',
                'ci.expires_at',
                'ci.created_at',
                'inviter.name as inviter_name'
            )
            ->orderBy('ci.created_at', 'desc')
            ->get();

        return Inertia::render('users/Index', [
            'company' => [
                'id' => $company->id,
                'name' => $company->name,
                'slug' => $company->slug,
            ],
            'users' => $users,
            'pendingInvitations' => $pendingInvitations,
            'currentUserRole' => $currentUserRole,
        ]);
    }

    public function invite(Request $request)
    {
        $data = $request->validate([
            'email' => ['required', 'email'],
            'role' => ['required', 'string', 'in:manager,accountant,operations'],
        ]);

        $company = CompanyContext::getCompany();

        // Owners and managers administer company membership.
        $currentUserRole = DB::table('auth.company_user')
            ->where('company_id', $company->id)
            ->where('user_id', Auth::id())
            ->value('role');

        if (! in_array($currentUserRole, ['owner', 'manager'], true)) {
            throw ValidationException::withMessages([
                'email' => 'Only owners and managers can invite users.',
            ]);
        }

        // Check if user already exists in company
        $existingUser = DB::table('auth.users')
            ->where('email', $data['email'])
            ->first();

        if ($existingUser) {
            $isMember = DB::table('auth.company_user')
                ->where('company_id', $company->id)
                ->where('user_id', $existingUser->id)
                ->exists();

            if ($isMember) {
                throw ValidationException::withMessages([
                    'email' => 'This user is already a member of the company.',
                ]);
            }
        }

        // Check if invitation already exists
        $existingInvitation = \App\Models\CompanyInvitation::where('company_id', $company->id)
            ->where('email', $data['email'])
            ->where('status', 'pending')
            ->where('expires_at', '>', now())
            ->first();

        if ($existingInvitation) {
            throw ValidationException::withMessages([
                'email' => 'An invitation has already been sent to this email address.',
            ]);
        }

        // Create invitation
        $invitation = \App\Models\CompanyInvitation::create([
            'company_id' => $company->id,
            'email' => $data['email'],
            'role' => $data['role'],
            'token' => \Illuminate\Support\Str::random(64),
            'invited_by_user_id' => Auth::id(),
            'expires_at' => now()->addDays(7),
            'status' => 'pending',
        ]);

        // TODO: Send invitation email

        return back()->with('success', 'Invitation sent successfully.');
    }

    public function updateRole(Request $request, string $userId)
    {
        $data = $request->validate([
            'role' => ['required', 'string', 'in:manager,accountant,operations'],
        ]);

        $company = CompanyContext::getCompany();

        // Get current user's role
        $currentUserRole = DB::table('auth.company_user')
            ->where('company_id', $company->id)
            ->where('user_id', Auth::id())
            ->value('role');

        if (! in_array($currentUserRole, ['owner', 'manager'], true)) {
            abort(403, 'Only owners and managers can change user roles.');
        }

        // Get target user's current role
        $targetUserRole = DB::table('auth.company_user')
            ->where('company_id', $company->id)
            ->where('user_id', $userId)
            ->value('role');

        if (! $targetUserRole) {
            abort(404, 'User not found in this company.');
        }

        // Owners cannot be changed by anyone
        if ($targetUserRole === 'owner') {
            abort(403, 'The owner role cannot be changed.');
        }

        // Managers may manage peers and subordinate roles, but never an owner.
        if ($currentUserRole === 'manager' && $targetUserRole === 'owner') {
            abort(403, 'Managers cannot change an owner.');
        }

        // Update the role
        DB::table('auth.company_user')
            ->where('company_id', $company->id)
            ->where('user_id', $userId)
            ->update([
                'role' => $data['role'],
                'updated_at' => now(),
            ]);

        return back()->with('success', 'User role updated successfully.');
    }

    public function remove(Request $request, string $userId)
    {
        $company = CompanyContext::getCompany();

        // Check if current user is owner or manager
        $currentUserRole = DB::table('auth.company_user')
            ->where('company_id', $company->id)
            ->where('user_id', Auth::id())
            ->value('role');

        if (! in_array($currentUserRole, ['owner', 'manager'], true)) {
            abort(403, 'Only owners and managers can remove users.');
        }

        // Don't allow removing yourself
        if ($userId === Auth::id()) {
            abort(403, 'You cannot remove yourself from the company.');
        }

        // Get target user's role
        $targetUserRole = DB::table('auth.company_user')
            ->where('company_id', $company->id)
            ->where('user_id', $userId)
            ->value('role');

        // Ownership is protected. Transfer ownership explicitly before removal.
        if ($targetUserRole === 'owner') {
            abort(403, 'The owner cannot be removed. Transfer ownership first.');
        }

        DB::table('auth.company_user')
            ->where('company_id', $company->id)
            ->where('user_id', $userId)
            ->delete();

        return back()->with('success', 'User removed from company successfully.');
    }
}
