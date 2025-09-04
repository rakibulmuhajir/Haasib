<?php

namespace App\Actions\Company;

use App\Actions\Company\InviteUser;
use App\Models\User;
use Illuminate\Support\Facades\Validator;

class CompanyInvite
{
    public function __construct(private InviteUser $inviter)
    {
    }

    public function handle(array $p, User $actor): array
    {
        $data = Validator::make($p, [
            'company' => 'required|string',
            'email' => 'required|email',
            'role' => 'required|in:owner,admin,accountant,viewer,member',
            'expires_in_days' => 'nullable|integer|min:1',
        ])->validate();

        $inv = $this->inviter->handle($data['company'], $data, $actor);

        return [
            'message' => 'Invitation created',
            'data' => [
                'id' => $inv['id'],
                'email' => $inv['email'],
                'role' => $inv['role'],
                'status' => $inv['status'],
            ],
        ];
    }
}
