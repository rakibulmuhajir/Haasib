<?php

namespace App\Http\Controllers;

use App\Models\Company;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class InvitationController extends Controller
{
    public function companyInvitations(Request $request, string $company)
    {
        $auth = $request->user();

        // Resolve company by id or slug or name
        $q = \App\Models\Company::query();
        if (\Illuminate\Support\Str::isUuid($company)) {
            $q->where('id', $company);
        } else {
            $q->where(function ($w) use ($company) {
                $w->where('slug', $company)->orWhere('name', $company);
            });
        }
        $co = $q->firstOrFail(['id','name','slug']);

        // Permission: owner/admin or superadmin
        $allowed = $auth->isSuperAdmin() || \Illuminate\Support\Facades\DB::table('auth.company_user')
            ->where('company_id', $co->id)
            ->where('user_id', $auth->id)
            ->whereIn('role', ['owner','admin'])
            ->exists();
        abort_unless($allowed, 403);

        $status = $request->query('status', 'pending');

        $rows = \Illuminate\Support\Facades\DB::table('auth.company_invitations as i')
            ->leftJoin('users as u', 'u.id', '=', 'i.invited_by_user_id')
            ->where('i.company_id', $co->id)
            ->when($status, fn($w) => $w->where('i.status', $status))
            ->orderByDesc('i.created_at')
            ->get(['i.id','i.invited_email as email','i.role','i.status','i.expires_at','i.created_at','u.name as invited_by']);

        return response()->json(['data' => $rows]);
    }
    public function myInvitations(Request $request)
    {
        $user = $request->user();
        $email = Str::lower($user->email);

        $rows = DB::table('auth.company_invitations as i')
            ->join('auth.companies as c', 'c.id', '=', 'i.company_id')
            ->leftJoin('users as u', 'u.id', '=', 'i.invited_by_user_id')
            ->where('i.status', 'pending')
            ->where('i.invited_email', $email)
            ->where(function ($q) {
                $q->whereNull('i.expires_at')->orWhere('i.expires_at', '>', now());
            })
            ->orderBy('i.created_at', 'desc')
            ->get(['i.id','i.company_id','c.name as company_name','i.role','i.expires_at','i.created_at','u.name as invited_by','i.token']);

        return response()->json(['data' => $rows]);
    }

    public function accept(Request $request, string $token)
    {
        $auth = $request->user();
        $now = now();

        $inv = DB::table('auth.company_invitations')->where('token', $token)->first();
        abort_unless($inv, 404, 'Invitation not found');

        // Expiry / status checks
        abort_if($inv->status !== 'pending', 422, 'Invitation is not pending');
        if ($inv->expires_at && $inv->expires_at <= $now) {
            DB::table('auth.company_invitations')->where('id', $inv->id)->update(['status' => 'expired', 'updated_at' => $now]);
            abort(422, 'Invitation expired');
        }

        // Only the invited user can accept; superadmin can override by providing ?user_id=...
        $targetUserId = $auth->id;
        if ($auth->isSuperAdmin() && $request->filled('user_id')) {
            $targetUserId = $request->query('user_id');
        } else {
            // Ensure email matches
            abort_unless(Str::lower($auth->email) === Str::lower($inv->invited_email), 403, 'This invite is for a different email');
        }

        // Attach membership idempotently; update role if exists
        DB::transaction(function () use ($inv, $targetUserId, $now, $auth) {
            $exists = DB::table('auth.company_user')
                ->where('company_id', $inv->company_id)
                ->where('user_id', $targetUserId)
                ->exists();

            if ($exists) {
                DB::table('auth.company_user')
                    ->where('company_id', $inv->company_id)
                    ->where('user_id', $targetUserId)
                    ->update([
                        'role' => $inv->role,
                        'invited_by_user_id' => $inv->invited_by_user_id ?? $auth->id,
                        'updated_at' => $now,
                    ]);
            } else {
                DB::table('auth.company_user')->insert([
                    'company_id' => $inv->company_id,
                    'user_id' => $targetUserId,
                    'role' => $inv->role,
                    'invited_by_user_id' => $inv->invited_by_user_id ?? $auth->id,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
            }

            DB::table('auth.company_invitations')->where('id', $inv->id)->update([
                'status' => 'accepted',
                'accepted_at' => $now,
                'accepted_by_user_id' => $targetUserId,
                'updated_at' => $now,
            ]);
        });

        return response()->json(['message' => 'Invitation accepted', 'company_id' => $inv->company_id]);
    }

    public function revoke(Request $request, string $id)
    {
        $auth = $request->user();
        $inv = DB::table('auth.company_invitations')->where('id', $id)->first();
        abort_unless($inv, 404);

        // Permission: inviter, an admin/owner of company, or superadmin
        $allowed = $auth->isSuperAdmin() || $inv->invited_by_user_id === $auth->id || DB::table('auth.company_user')
            ->where('company_id', $inv->company_id)
            ->where('user_id', $auth->id)
            ->whereIn('role', ['owner','admin'])
            ->exists();
        abort_unless($allowed, 403);

        DB::table('auth.company_invitations')->where('id', $id)->update(['status' => 'revoked', 'updated_at' => now()]);
        return response()->json(['message' => 'Invitation revoked']);
    }
}
