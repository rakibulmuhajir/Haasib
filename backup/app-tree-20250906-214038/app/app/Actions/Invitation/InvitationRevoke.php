<?php

namespace App\Actions\Invitation;

use App\Models\CompanyInvitation;
use App\Models\User;
use App\Services\InvitationService;
use Illuminate\Support\Facades\Validator;

class InvitationRevoke
{
    public function __construct(private InvitationService $service)
    {
    }

    public function handle(array $p, User $actor): array
    {
        $data = Validator::make($p, [
            'id' => 'required|string',
        ])->validate();

        $inv = CompanyInvitation::findOrFail($data['id']);

        $this->service->revoke($actor, $inv->id);

        return [
            'message' => 'Invitation revoked',
            'data' => [
                'id' => $inv->id,
                'email' => $inv->invited_email,
                'role' => $inv->role,
                'status' => 'revoked',
            ],
        ];
    }
}
