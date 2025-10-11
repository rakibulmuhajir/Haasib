<?php

namespace App\Actions\Invitation;

use App\Models\CompanyInvitation;
use App\Models\User;
use App\Services\CompanyPermissionService;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class InvitationRevoke
{
    public function __construct(
        private CompanyPermissionService $permissionService
    ) {}

    public function execute(CompanyInvitation $invitation, User $user, ?string $reason = null): array
    {
        // Check permissions
        if (! $this->permissionService->userHasCompanyPermission($user, $invitation->company, 'company.invite')) {
            throw new \Illuminate\Auth\Access\AuthorizationException('You do not have permission to revoke invitations for this company');
        }

        // Check if invitation can be revoked
        if (! $invitation->canBeRevoked()) {
            throw new \InvalidArgumentException('This invitation cannot be revoked in its current state');
        }

        try {
            DB::beginTransaction();

            // Update invitation status
            $invitation->update([
                'status' => 'revoked',
                'accepted_by_user_id' => $user->id,
                'accepted_at' => now(),
            ]);

            // Log revocation
            Log::info('Company invitation revoked', [
                'invitation_id' => $invitation->id,
                'company_id' => $invitation->company_id,
                'email' => $invitation->email,
                'role' => $invitation->role,
                'revoked_by' => $user->id,
                'revoked_by_name' => $user->name,
                'reason' => $reason,
            ]);

            DB::commit();

            return [
                'success' => true,
                'message' => 'Invitation revoked successfully',
                'invitation' => $invitation->fresh(),
                'reason' => $reason,
            ];
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Failed to revoke invitation', [
                'invitation_id' => $invitation->id,
                'user_id' => $user->id,
                'error' => $e->getMessage(),
            ]);

            throw new \RuntimeException('Failed to revoke invitation: ' . $e->getMessage());
        }
    }
}