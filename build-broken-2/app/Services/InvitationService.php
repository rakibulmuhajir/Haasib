<?php

namespace App\Services;

use App\Mail\InvitationMail;
use App\Models\Company;
use App\Models\Invitation;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

class InvitationService
{
    /**
     * Send an invitation to join a company.
     */
    public function sendInvitation(
        Company $company,
        string $email,
        string $role,
        User $inviter
    ): Invitation {
        // Validate input
        $this->validateInvitation($company, $email, $role, $inviter);

        DB::beginTransaction();

        try {
            // Create the invitation
            $invitation = Invitation::create([
                'company_id' => $company->id,
                'inviter_user_id' => $inviter->id,
                'email' => strtolower($email),
                'role' => $role,
                'status' => 'pending',
                'expires_at' => now()->addDays(7),
                'metadata' => [
                    'invited_at' => now()->toISOString(),
                    'inviter_name' => $inviter->name,
                    'company_name' => $company->name,
                ],
            ]);

            // Send the email
            Mail::to($email)->send(new InvitationMail($invitation));

            DB::commit();

            return $invitation;
        } catch (\Exception $e) {
            DB::rollBack();
            throw $e;
        }
    }

    /**
     * Accept an invitation.
     */
    public function acceptInvitation(string $token, User $user): bool
    {
        $invitation = $this->findValidInvitation($token);

        if (!$invitation) {
            return false;
        }

        // Verify the email matches
        if (strtolower($invitation->email) !== strtolower($user->email)) {
            throw ValidationException::withMessages([
                'email' => 'This invitation was sent to a different email address.'
            ]);
        }

        DB::beginTransaction();

        try {
            // Accept the invitation (this will create the company-user relationship)
            $success = $invitation->accept($user);

            if ($success) {
                DB::commit();
                return true;
            }

            DB::rollBack();
            return false;
        } catch (\Exception $e) {
            DB::rollBack();
            throw $e;
        }
    }

    /**
     * Decline an invitation.
     */
    public function declineInvitation(string $token): bool
    {
        $invitation = $this->findValidInvitation($token);

        if (!$invitation) {
            return false;
        }

        return $invitation->decline();
    }

    /**
     * Get pending invitations for a user.
     */
    public function getPendingInvitationsForUser(User $user): \Illuminate\Database\Eloquent\Collection
    {
        return $user->pendingInvitations()->with(['company', 'inviter'])->get();
    }

    /**
     * Get pending invitations for a company.
     */
    public function getPendingInvitationsForCompany(Company $company): \Illuminate\Database\Eloquent\Collection
    {
        return $company->invitations()
                      ->pending()
                      ->with('inviter')
                      ->orderBy('created_at', 'desc')
                      ->get();
    }

    /**
     * Cancel/revoke an invitation.
     */
    public function cancelInvitation(string $invitationId, User $user): bool
    {
        $invitation = Invitation::where('id', $invitationId)
                                ->where('status', 'pending')
                                ->first();

        if (!$invitation) {
            return false;
        }

        // Check if user has permission to cancel this invitation
        if (!$this->canManageInvitation($invitation, $user)) {
            throw ValidationException::withMessages([
                'permission' => 'You do not have permission to cancel this invitation.'
            ]);
        }

        $invitation->update(['status' => 'declined']);
        return true;
    }

    /**
     * Resend an invitation.
     */
    public function resendInvitation(string $invitationId, User $user): bool
    {
        $invitation = Invitation::where('id', $invitationId)
                                ->where('status', 'pending')
                                ->with(['company', 'inviter'])
                                ->first();

        if (!$invitation || !$invitation->isPending()) {
            return false;
        }

        // Check if user has permission to resend this invitation
        if (!$this->canManageInvitation($invitation, $user)) {
            throw ValidationException::withMessages([
                'permission' => 'You do not have permission to resend this invitation.'
            ]);
        }

        // Update expiration date
        $invitation->update(['expires_at' => now()->addDays(7)]);

        // Resend the email
        Mail::to($invitation->email)->send(new InvitationMail($invitation));

        return true;
    }

    /**
     * Mark expired invitations.
     */
    public function markExpiredInvitations(): int
    {
        return Invitation::expired()->update(['status' => 'expired']);
    }

    /**
     * Find a valid invitation by token.
     */
    private function findValidInvitation(string $token): ?Invitation
    {
        return Invitation::where('token', $token)
                        ->where('status', 'pending')
                        ->where('expires_at', '>', now())
                        ->with(['company', 'inviter'])
                        ->first();
    }

    /**
     * Validate invitation data.
     */
    private function validateInvitation(Company $company, string $email, string $role, User $inviter): void
    {
        // Check if inviter has permission to invite users to this company
        if (!$inviter->isOwnerOfCompany($company) && !$inviter->isAdminOfCompany($company)) {
            throw ValidationException::withMessages([
                'permission' => 'You do not have permission to invite users to this company.'
            ]);
        }

        // Check if user is already a member of the company
        $existingUser = User::where('email', $email)->first();
        if ($existingUser && $existingUser->companies()->where('company_id', $company->id)->exists()) {
            throw ValidationException::withMessages([
                'email' => 'This user is already a member of this company.'
            ]);
        }

        // Check if there's already a pending invitation for this email/company
        $existingInvitation = Invitation::where('email', $email)
                                      ->where('company_id', $company->id)
                                      ->where('status', 'pending')
                                      ->exists();

        if ($existingInvitation) {
            throw ValidationException::withMessages([
                'email' => 'There is already a pending invitation for this email address.'
            ]);
        }

        // Validate role
        $allowedRoles = [
            'company_owner', 'company_admin', 'accounting_admin', 
            'accounting_operator', 'accounting_viewer', 'portal_customer', 'portal_vendor'
        ];

        if (!in_array($role, $allowedRoles)) {
            throw ValidationException::withMessages([
                'role' => 'Invalid role specified.'
            ]);
        }
    }

    /**
     * Check if user can manage (cancel/resend) an invitation.
     */
    private function canManageInvitation(Invitation $invitation, User $user): bool
    {
        // Owner or admin of the company can manage invitations
        if ($user->isOwnerOfCompany($invitation->company) || $user->isAdminOfCompany($invitation->company)) {
            return true;
        }

        // The person who sent the invitation can manage it
        if ($invitation->inviter_user_id === $user->id) {
            return true;
        }

        return false;
    }
}