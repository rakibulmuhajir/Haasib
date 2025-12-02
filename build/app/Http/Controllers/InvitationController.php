<?php

namespace App\Http\Controllers;

use App\Facades\CompanyContext;
use App\Models\CompanyInvitation;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class InvitationController extends Controller
{
    /**
     * Show the invitation acceptance page.
     */
    public function show(string $token): Response
    {
        $invitation = CompanyInvitation::where('token', $token)
            ->with(['company', 'inviter'])
            ->firstOrFail();

        return Inertia::render('invitation/Accept', [
            'invitation' => [
                'id' => $invitation->id,
                'email' => $invitation->email,
                'role' => $invitation->role,
                'status' => $invitation->status,
                'expires_at' => $invitation->expires_at->toIso8601String(),
                'is_expired' => $invitation->isExpired(),
                'is_valid' => $invitation->isValid(),
                'company' => [
                    'id' => $invitation->company->id,
                    'name' => $invitation->company->name,
                    'slug' => $invitation->company->slug,
                ],
                'inviter' => [
                    'name' => $invitation->inviter->name,
                    'email' => $invitation->inviter->email,
                ],
            ],
            'token' => $token,
        ]);
    }

    /**
     * Accept an invitation.
     */
    public function accept(Request $request, string $token)
    {
        $invitation = CompanyInvitation::where('token', $token)
            ->with('company')
            ->firstOrFail();

        if (! $invitation->isValid()) {
            return response()->json([
                'success' => false,
                'message' => 'This invitation has expired or is no longer valid.',
            ], 422);
        }

        $user = Auth::user();

        // Check if the invitation is for this user
        if ($user->email !== $invitation->email) {
            return response()->json([
                'success' => false,
                'message' => 'This invitation is not for your email address.',
            ], 403);
        }

        // Check if user is already a member
        $existingMembership = DB::table('auth.company_user')
            ->where('company_id', $invitation->company_id)
            ->where('user_id', $user->id)
            ->first();

        if ($existingMembership) {
            return response()->json([
                'success' => false,
                'message' => 'You are already a member of this company.',
            ], 422);
        }

        // Accept the invitation and add user to company
        DB::transaction(function () use ($invitation, $user) {
            $invitation->accept($user);

            DB::table('auth.company_user')->insert([
                'company_id' => $invitation->company_id,
                'user_id' => $user->id,
                'role' => $invitation->role,
                'invited_by_user_id' => $invitation->invited_by_user_id,
                'joined_at' => now(),
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        });

        return redirect()
            ->route('company.show', ['company' => $invitation->company->slug])
            ->with('success', 'Invitation accepted successfully!');
    }

    /**
     * Reject an invitation.
     */
    public function reject(Request $request, string $token)
    {
        $invitation = CompanyInvitation::where('token', $token)->firstOrFail();

        if (! $invitation->isValid()) {
            return response()->json([
                'success' => false,
                'message' => 'This invitation has expired or is no longer valid.',
            ], 422);
        }

        $user = Auth::user();

        // Check if the invitation is for this user
        if ($user && $user->email !== $invitation->email) {
            return response()->json([
                'success' => false,
                'message' => 'This invitation is not for your email address.',
            ], 403);
        }

        $invitation->reject();

        return redirect()->route('dashboard')->with('success', 'Invitation rejected.');
    }

    /**
     * Get all pending invitations for the current user.
     */
    public function pending(Request $request): JsonResponse
    {
        $user = Auth::user();

        $invitations = CompanyInvitation::where('email', $user->email)
            ->where('status', 'pending')
            ->where('expires_at', '>', now())
            ->with(['company', 'inviter'])
            ->orderBy('created_at', 'desc')
            ->get()
            ->map(function ($invitation) {
                return [
                    'id' => $invitation->id,
                    'token' => $invitation->token,
                    'email' => $invitation->email,
                    'role' => $invitation->role,
                    'status' => $invitation->status,
                    'expires_at' => $invitation->expires_at->toIso8601String(),
                    'created_at' => $invitation->created_at->toIso8601String(),
                    'company' => [
                        'id' => $invitation->company->id,
                        'name' => $invitation->company->name,
                        'slug' => $invitation->company->slug,
                    ],
                    'inviter' => [
                        'name' => $invitation->inviter->name,
                        'email' => $invitation->inviter->email,
                    ],
                ];
            });

        return response()->json([
            'data' => $invitations,
        ]);
    }

    /**
     * Revoke a pending invitation.
     */
    public function revoke(Request $request, string $invitationId): JsonResponse
    {
        $company = CompanyContext::getCompany();

        // Check if user is owner or admin
        $currentUserRole = DB::table('auth.company_user')
            ->where('company_id', $company->id)
            ->where('user_id', Auth::id())
            ->value('role');

        if (! in_array($currentUserRole, ['owner', 'admin'])) {
            return response()->json([
                'success' => false,
                'message' => 'Only owners and admins can revoke invitations.',
            ], 403);
        }

        // Find the invitation and ensure it belongs to this company
        $invitation = CompanyInvitation::where('id', $invitationId)
            ->where('company_id', $company->id)
            ->first();

        if (! $invitation) {
            return response()->json([
                'success' => false,
                'message' => 'Invitation not found.',
            ], 404);
        }

        if ($invitation->status !== 'pending') {
            return response()->json([
                'success' => false,
                'message' => 'Only pending invitations can be revoked.',
            ], 422);
        }

        // Revoke the invitation
        $invitation->update([
            'status' => 'revoked',
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Invitation revoked successfully.',
        ]);
    }
}
