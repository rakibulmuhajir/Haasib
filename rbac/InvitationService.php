<?php

namespace App\Services;

use App\Models\Company;
use App\Models\Invitation;
use App\Models\User;
use App\Notifications\InvitationNotification;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class InvitationService
{
    public function __construct(
        private CompanyService $companyService
    ) {}

    /**
     * Create and send an invitation.
     */
    public function invite(Company $company, string $email, string $roleName, User $inviter): Invitation
    {
        // Check for existing pending invitation
        $existing = Invitation::where('company_id', $company->id)
            ->forEmail($email)
            ->pending()
            ->first();

        if ($existing) {
            // Revoke old and create new
            $existing->revoke();
        }

        // Check if user already a member
        $existingUser = User::where('email', strtolower($email))->first();
        if ($existingUser && $existingUser->belongsToCompany($company)) {
            throw new \Exception('User is already a member of this company.');
        }

        $invitation = Invitation::create([
            'company_id' => $company->id,
            'invited_by' => $inviter->id,
            'email' => strtolower($email),
            'role_name' => $roleName,
            'expires_at' => now()->addDays(7),
        ]);

        // Send notification
        if ($existingUser) {
            $existingUser->notify(new InvitationNotification($invitation));
        } else {
            // Send to email directly for non-users
            \Illuminate\Support\Facades\Notification::route('mail', $email)
                ->notify(new InvitationNotification($invitation));
        }

        AuditService::record('invitation.sent', $invitation, null, [
            'email' => $email,
            'role' => $roleName,
            'company_id' => $company->id,
        ]);

        return $invitation;
    }

    /**
     * Accept an invitation.
     */
    public function accept(string $token, ?User $user = null): array
    {
        $invitation = Invitation::where('token', $token)
            ->pending()
            ->firstOrFail();

        if ($invitation->isExpired()) {
            $invitation->markExpired();
            throw new \Exception('This invitation has expired.');
        }

        return DB::transaction(function () use ($invitation, $user) {
            // Get or create user
            if (!$user) {
                $user = User::where('email', $invitation->email)->first();
            }

            $isNewUser = false;
            if (!$user) {
                $isNewUser = true;
                $user = User::create([
                    'name' => explode('@', $invitation->email)[0],
                    'email' => $invitation->email,
                    'password' => bcrypt(Str::random(32)),
                ]);
            }

            // Add to company with role
            $this->companyService->addMember(
                $invitation->company,
                $user,
                $invitation->role_name
            );

            // Mark invitation as accepted
            $invitation->markAccepted();

            AuditService::record('invitation.accepted', $invitation, null, [
                'user_id' => $user->id,
                'company_id' => $invitation->company_id,
            ]);

            return [
                'user' => $user,
                'company' => $invitation->company,
                'is_new_user' => $isNewUser,
            ];
        });
    }

    /**
     * Revoke an invitation.
     */
    public function revoke(Invitation $invitation): void
    {
        $invitation->revoke();

        AuditService::record('invitation.revoked', $invitation);
    }

    /**
     * Get pending invitations for a company.
     */
    public function getPending(Company $company): \Illuminate\Database\Eloquent\Collection
    {
        return $company->invitations()
            ->pending()
            ->with('inviter:id,name')
            ->orderByDesc('created_at')
            ->get();
    }

    /**
     * Resend an invitation.
     */
    public function resend(Invitation $invitation): Invitation
    {
        if (!$invitation->isPending()) {
            throw new \Exception('Can only resend pending invitations.');
        }

        // Extend expiry
        $invitation->update([
            'expires_at' => now()->addDays(7),
        ]);

        // Resend notification
        $user = User::where('email', $invitation->email)->first();
        if ($user) {
            $user->notify(new InvitationNotification($invitation));
        } else {
            \Illuminate\Support\Facades\Notification::route('mail', $invitation->email)
                ->notify(new InvitationNotification($invitation));
        }

        return $invitation;
    }
}
