<?php

namespace App\Services;

use App\Models\Company;
use App\Models\User;
use App\Models\CompanyInvitation;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use App\Repositories\CompanyMembershipRepository;

class InvitationService
{
    public function __construct(protected CompanyLookupService $lookup) {}
    /**
     * List invitations for a given company.
     */
    public function listCompanyInvitations(User $auth, string $company, ?string $status = 'pending')
    {
        // Resolve company by id, slug or name
        $q = Company::query();
        if (Str::isUuid($company)) {
            $q->where('id', $company);
        } else {
            $q->where(function ($w) use ($company) {
                $w->where('slug', $company)->orWhere('name', $company);
            });
        }
        $co = $q->firstOrFail(['id', 'name', 'slug']);

        // Permission check: owner/admin or superadmin
        $allowed = $auth->isSuperAdmin() || $this->lookup->userHasRole($co->id, $auth->id, ['owner','admin']);
        abort_unless($allowed, 403);

        return CompanyInvitation::query()
            ->from('auth.company_invitations as i')
            ->leftJoin('users as u', 'u.id', '=', 'i.invited_by_user_id')
            ->where('i.company_id', $co->id)
            ->when($status, fn($w) => $w->where('i.status', $status))
            ->orderByDesc('i.created_at')
            ->get([
                'i.id',
                'i.invited_email as email',
                'i.role',
                'i.status',
                'i.expires_at',
                'i.created_at',
                'u.name as invited_by',
            ]);
    }

    /**
     * List invitations for the authenticated user.
     */
    public function listUserInvitations(User $user)
    {
        $email = Str::lower($user->email);

        return CompanyInvitation::query()
            ->from('auth.company_invitations as i')
            ->join('auth.companies as c', 'c.id', '=', 'i.company_id')
            ->leftJoin('users as u', 'u.id', '=', 'i.invited_by_user_id')
            ->where('i.status', 'pending')
            ->where('i.invited_email', $email)
            ->where(function ($q) {
                $q->whereNull('i.expires_at')->orWhere('i.expires_at', '>', now());
            })
            ->orderBy('i.created_at', 'desc')
            ->get([
                'i.id',
                'i.company_id',
                'c.name as company_name',
                'i.role',
                'i.expires_at',
                'i.created_at',
                'u.name as invited_by',
                'i.token',
            ]);
    }

    /**
     * Accept an invitation and attach user to company.
     */
    public function accept(User $auth, string $token, ?string $userId = null): string
    {
        $now = now();

        $inv = CompanyInvitation::where('token', $token)->first();
        abort_unless($inv, 404, 'Invitation not found');

        abort_if($inv->status !== 'pending', 422, 'Invitation is not pending');
        if ($inv->expires_at && $inv->expires_at <= $now) {
            $inv->update(['status' => 'expired', 'updated_at' => $now]);
            abort(422, 'Invitation expired');
        }

        // Determine target user
        $targetUserId = $auth->id;
        if ($auth->isSuperAdmin() && $userId) {
            $targetUserId = $userId;
        } else {
            abort_unless(Str::lower($auth->email) === Str::lower($inv->invited_email), 403, 'This invite is for a different email');
        }

        DB::transaction(function () use ($inv, $targetUserId, $auth) {
            $this->lookup->upsertMember($inv->company_id, $targetUserId, [
                'role' => $inv->role,
                'invited_by_user_id' => $inv->invited_by_user_id ?? $auth->id,
            ]);

            $inv->update([
                'status' => 'accepted',
                'accepted_at' => now(),
                'accepted_by_user_id' => $targetUserId,
                'updated_at' => now(),
            ]);
        });

        return $inv->company_id;
    }

    /**
     * Revoke an invitation.
     */
    public function revoke(User $auth, string $id): void
    {
        $inv = CompanyInvitation::find($id);
        abort_unless($inv, 404);

        // Permission: inviter, admin/owner of company, or superadmin
        $allowed = $auth->isSuperAdmin() || $inv->invited_by_user_id === $auth->id || $this->lookup->userHasRole($inv->company_id, $auth->id, ['owner','admin']);
        abort_unless($allowed, 403);

        $inv->update(['status' => 'revoked', 'updated_at' => now()]);
    }
}

