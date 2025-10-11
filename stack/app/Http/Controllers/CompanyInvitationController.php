<?php

namespace App\Http\Controllers;

use App\Actions\Company\CompanyInvite;
use App\Enums\CompanyRole;
use App\Http\Requests\CompanyInvitationRequest;
use App\Models\Company;
use App\Models\CompanyInvitation;
use App\Services\AuthService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CompanyInvitationController extends Controller
{
    public function __construct(
        private readonly AuthService $authService
    ) {}

    /**
     * Get all invitations for a company.
     */
    public function index(Request $request, string $companyId): JsonResponse
    {
        $user = $request->user();
        $company = Company::findOrFail($companyId);

        if (!$this->authService->canAccessCompany($user, $company)) {
            return response()->json([
                'message' => 'Access denied to this company.',
            ], 403);
        }

        $invitations = CompanyInvitation::where('company_id', $company->id)
            ->with('inviter')
            ->orderBy('created_at', 'desc')
            ->get()
            ->map(function ($invitation) {
                return [
                    'id' => $invitation->id,
                    'email' => $invitation->email,
                    'role' => $invitation->role,
                    'status' => $invitation->status,
                    'message' => $invitation->message,
                    'expires_at' => $invitation->expires_at,
                    'is_expired' => $invitation->isExpired(),
                    'created_at' => $invitation->created_at,
                    'inviter' => [
                        'id' => $invitation->inviter->id,
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
     * Create a new company invitation.
     */
    public function store(CompanyInvitationRequest $request, string $companyId): JsonResponse
    {
        $user = $request->user();
        $company = Company::findOrFail($companyId);

        if (!$this->authService->canAccessCompany($user, $company)) {
            return response()->json([
                'message' => 'Access denied to this company.',
            ], 403);
        }

        try {
            $invite = new CompanyInvite(
                inviter: $user,
                company: $company,
                email: $request->email,
                role: CompanyRole::from($request->role),
                message: $request->message,
                expiresInDays: $request->expires_in_days ?? 7
            );

            $invitation = $invite->execute();

            return response()->json([
                'data' => [
                    'id' => $invitation->id,
                    'email' => $invitation->email,
                    'role' => $invitation->role,
                    'message' => $invitation->message,
                    'expires_at' => $invitation->expires_at,
                    'token' => $invitation->token,
                    'status' => $invitation->status,
                    'created_at' => $invitation->created_at,
                ],
                'meta' => [
                    'message' => 'Invitation sent successfully',
                ],
            ], 201);

        } catch (\Exception $e) {
            return response()->json([
                'message' => $e->getMessage(),
            ], 422);
        }
    }

    /**
     * Get a specific invitation.
     */
    public function show(Request $request, string $companyId, string $invitationId): JsonResponse
    {
        $user = $request->user();
        $company = Company::findOrFail($companyId);

        if (!$this->authService->canAccessCompany($user, $company)) {
            return response()->json([
                'message' => 'Access denied to this company.',
            ], 403);
        }

        $invitation = CompanyInvitation::where('company_id', $company->id)
            ->where('id', $invitationId)
            ->with('inviter')
            ->firstOrFail();

        return response()->json([
            'data' => [
                'id' => $invitation->id,
                'email' => $invitation->email,
                'role' => $invitation->role,
                'status' => $invitation->status,
                'message' => $invitation->message,
                'expires_at' => $invitation->expires_at,
                'is_expired' => $invitation->isExpired(),
                'is_valid' => $invitation->isValid(),
                'token' => $invitation->token,
                'created_at' => $invitation->created_at,
                'updated_at' => $invitation->updated_at,
                'inviter' => [
                    'id' => $invitation->inviter->id,
                    'name' => $invitation->inviter->name,
                    'email' => $invitation->inviter->email,
                ],
            ],
        ]);
    }

    /**
     * Cancel/delete an invitation.
     */
    public function destroy(Request $request, string $companyId, string $invitationId): JsonResponse
    {
        $user = $request->user();
        $company = Company::findOrFail($companyId);

        if (!$this->authService->canAccessCompany($user, $company)) {
            return response()->json([
                'message' => 'Access denied to this company.',
            ], 403);
        }

        $invitation = CompanyInvitation::where('company_id', $company->id)
            ->where('id', $invitationId)
            ->firstOrFail();

        // Check if user can cancel invitations
        $userRole = $this->authService->getUserRole($user, $company);
        if (!in_array($userRole, ['owner', 'admin'])) {
            return response()->json([
                'message' => 'You do not have permission to cancel invitations.',
            ], 403);
        }

        $invitation->delete();

        return response()->json([
            'message' => 'Invitation cancelled successfully',
        ]);
    }

    /**
     * Accept an invitation.
     */
    public function accept(Request $request, string $token): JsonResponse
    {
        $invitation = CompanyInvitation::where('token', $token)
            ->with('company')
            ->firstOrFail();

        if (!$invitation->isValid()) {
            return response()->json([
                'message' => 'Invalid or expired invitation.',
            ], 422);
        }

        $user = $request->user();
        
        // Check if the invitation is for this user
        if ($user->email !== $invitation->email) {
            return response()->json([
                'message' => 'This invitation is not for your email address.',
            ], 403);
        }

        // Accept the invitation
        $invitation->accept();

        // Add user to company
        $invitation->company->users()->attach($user->id, [
            'role' => $invitation->role,
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return response()->json([
            'message' => 'Invitation accepted successfully',
            'data' => [
                'company' => [
                    'id' => $invitation->company->id,
                    'name' => $invitation->company->name,
                    'role' => $invitation->role,
                ],
            ],
        ]);
    }

    /**
     * Reject an invitation.
     */
    public function reject(Request $request, string $token): JsonResponse
    {
        $invitation = CompanyInvitation::where('token', $token)->firstOrFail();

        if (!$invitation->isValid()) {
            return response()->json([
                'message' => 'Invalid or expired invitation.',
            ], 422);
        }

        $user = $request->user();
        
        // Check if the invitation is for this user
        if ($user->email !== $invitation->email) {
            return response()->json([
                'message' => 'This invitation is not for your email address.',
            ], 403);
        }

        // Reject the invitation
        $invitation->reject();

        return response()->json([
            'message' => 'Invitation rejected',
        ]);
    }
}