<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CompanyInvitationResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $user = $request->user();
        $canManage = $this->userCanManageInvitation($user);

        return [
            'id' => $this->id,
            'email' => $this->email,
            'role' => $this->role,
            'status' => $this->status,
            'expires_at' => $this->expires_at,
            'accepted_at' => $this->accepted_at,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,

            // Relationships
            'company' => $this->whenLoaded('company', function () {
                return [
                    'id' => $this->company->id,
                    'name' => $this->company->name,
                    'slug' => $this->company->slug,
                    'industry' => $this->company->industry,
                ];
            }),

            'invited_by' => $this->whenLoaded('invitedBy', function () {
                return [
                    'id' => $this->invitedBy->id,
                    'name' => $this->invitedBy->name,
                    'email' => $this->invitedBy->email,
                ];
            }),

            'accepted_by' => $this->whenLoaded('acceptedBy', function () {
                return $this->acceptedBy ? [
                    'id' => $this->acceptedBy->id,
                    'name' => $this->acceptedBy->name,
                    'email' => $this->acceptedBy->email,
                ] : null;
            }),

            // Conditional data based on user permissions
            'token' => $this->when($this->token && $canManage, $this->token),
            'accept_url' => $this->when($this->status === 'pending', function () {
                return route('invitations.accept', $this->token);
            }),
            'reject_url' => $this->when($this->status === 'pending', function () {
                return route('invitations.reject', $this->token);
            }),

            // Status calculations
            'is_expired' => $this->isExpired(),
            'is_valid' => $this->isValid(),
            'days_until_expiry' => $this->when($this->status === 'pending', function () {
                return now()->diffInDays($this->expires_at, false);
            }),

            // Permissions
            'can_manage' => $canManage,
            'can_cancel' => $canManage && $this->status === 'pending',
            'can_resend' => $canManage && $this->status === 'pending',
            'can_accept' => $user && $this->email === $user->email && $this->status === 'pending',
            'can_reject' => $user && $this->email === $user->email && $this->status === 'pending',

            // Links
            '_links' => [
                'self' => route('companies.invitations.show', [$this->company_id, $this->id]),
                'company' => route('companies.show', $this->company_id),
                'accept' => $this->when($this->status === 'pending', route('invitations.accept', $this->token)),
                'reject' => $this->when($this->status === 'pending', route('invitations.reject', $this->token)),
            ],
        ];
    }

    private function userCanManageInvitation(?\App\Models\User $user): bool
    {
        if (! $user) {
            return false;
        }

        // Super admins can manage all invitations
        if (in_array($user->system_role, ['system_owner', 'super_admin'])) {
            return true;
        }

        // Company owners and admins can manage invitations
        $companyUser = $this->company->users->where('id', $user->id)->first();
        return $companyUser && in_array($companyUser->role, ['owner', 'admin']);
    }
}