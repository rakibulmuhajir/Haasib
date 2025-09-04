<?php

namespace App\Http\Controllers;

use App\Services\InvitationService;
use Illuminate\Http\Request;

class InvitationController extends Controller
{
    public function __construct(private InvitationService $invitations)
    {
    }

    public function companyInvitations(Request $request, string $company)
    {
        $rows = $this->invitations->listCompanyInvitations(
            $request->user(),
            $company,
            $request->query('status', 'pending'),
        );

        return response()->json(['data' => $rows]);
    }

    public function myInvitations(Request $request)
    {
        $rows = $this->invitations->listUserInvitations($request->user());

        return response()->json(['data' => $rows]);
    }

    public function accept(Request $request, string $token)
    {
        $companyId = $this->invitations->accept(
            $request->user(),
            $token,
            $request->query('user_id'),
        );

        return response()->json(['message' => 'Invitation accepted', 'company_id' => $companyId]);
    }

    public function revoke(Request $request, string $id)
    {
        $this->invitations->revoke($request->user(), $id);

        return response()->json(['message' => 'Invitation revoked']);
    }
}
