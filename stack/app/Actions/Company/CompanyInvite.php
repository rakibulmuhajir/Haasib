<?php

namespace App\Actions\Company;

use App\Enums\CompanyRole;
use App\Models\Company;
use App\Models\CompanyInvitation;
use App\Models\User;
use Illuminate\Support\Str;

class CompanyInvite
{
    public function __construct(
        private readonly User $inviter,
        private readonly Company $company,
        private readonly string $email,
        private readonly CompanyRole $role,
        private readonly ?string $message = null,
        private readonly int $expiresInDays = 7
    ) {}

    public function execute(): CompanyInvitation
    {
        // Validate that inviter can invite users
        $this->validateInviterPermissions();

        // Check if user is already a member
        $this->validateUserNotMember();

        // Check for existing pending invitation
        $this->validateNoPendingInvitation();

        // Create the invitation
        $invitation = CompanyInvitation::create([
            'company_id' => $this->company->id,
            'email' => $this->email,
            'role' => $this->role->value,
            'token' => Str::random(32),
            'message' => $this->message,
            'expires_at' => now()->addDays($this->expiresInDays),
            'invited_by_user_id' => $this->inviter->id,
            'status' => 'pending',
        ]);

        return $invitation;
    }

    private function validateInviterPermissions(): void
    {
        $inviterRole = $this->inviter->companies()
            ->where('company_id', $this->company->id)
            ->first()?->pivot->role;

        if (!$inviterRole) {
            throw new \InvalidArgumentException('You are not a member of this company.');
        }

        // Owners and admins can invite users
        if (!in_array($inviterRole, ['owner', 'admin'])) {
            throw new \InvalidArgumentException('You do not have permission to invite users to this company.');
        }

        // Only owners can invite other owners
        if ($this->role === CompanyRole::Owner && $inviterRole !== 'owner') {
            throw new \InvalidArgumentException('You do not have permission to assign this role.');
        }
    }

    private function validateUserNotMember(): void
    {
        $existingUser = User::where('email', $this->email)->first();
        
        if ($existingUser) {
            $isMember = $this->company->users()
                ->where('user_id', $existingUser->id)
                ->exists();

            if ($isMember) {
                throw new \InvalidArgumentException('User is already a member of this company.');
            }
        }
    }

    private function validateNoPendingInvitation(): void
    {
        $existingInvitation = CompanyInvitation::where('company_id', $this->company->id)
            ->where('email', $this->email)
            ->where('status', 'pending')
            ->exists();

        if ($existingInvitation) {
            throw new \InvalidArgumentException('An invitation for this email already exists.');
        }
    }
}