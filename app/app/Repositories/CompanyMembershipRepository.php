<?php

namespace App\Repositories;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Collection;

class CompanyMembershipRepository
{
    public function verifyMembership(string $userId, string $companyId): bool
    {
        return DB::table('auth.company_user')
            ->where('user_id', $userId)
            ->where('company_id', $companyId)
            ->exists();
    }

    public function roleForUser(string $userId, string $companyId): ?string
    {
        return DB::table('auth.company_user')
            ->where('user_id', $userId)
            ->where('company_id', $companyId)
            ->value('role');
    }

    public function memberships(string $userId): Collection
    {
        return DB::table('auth.company_user as cu')
            ->join('auth.companies as c', 'c.id', '=', 'cu.company_id')
            ->where('cu.user_id', $userId)
            ->orderBy('c.name')
            ->get([
                'c.id',
                'c.name',
                'c.slug',
                'cu.role',
                'cu.created_at',
                'cu.updated_at',
            ]);
    }

    public function membersPreview(string $companyId, int $limit = 10): Collection
    {
        return DB::table('auth.company_user as cu')
            ->join('users as u', 'u.id', '=', 'cu.user_id')
            ->where('cu.company_id', $companyId)
            ->select('u.id', 'u.name', 'u.email', 'cu.role')
            ->orderBy('u.name')
            ->limit($limit)
            ->get();
    }

    public function owners(string $companyId): Collection
    {
        return DB::table('auth.company_user as cu')
            ->join('users as u', 'u.id', '=', 'cu.user_id')
            ->where('cu.company_id', $companyId)
            ->where('cu.role', 'owner')
            ->select('u.id', 'u.name', 'u.email')
            ->orderBy('u.name')
            ->get();
    }

    public function roleCounts(string $companyId): Collection
    {
        return DB::table('auth.company_user')
            ->select('role', DB::raw('count(*) as cnt'))
            ->where('company_id', $companyId)
            ->groupBy('role')
            ->pluck('cnt', 'role');
    }

    public function countMembers(string $companyId): int
    {
        return DB::table('auth.company_user')
            ->where('company_id', $companyId)
            ->count();
    }

    public function searchMembers(string $companyId, string $q, int $limit = 10): Collection
    {
        $like = '%'.str_replace(['%', '_'], ['\\%', '\\_'], $q).'%';
        return DB::table('auth.company_user as cu')
            ->join('users as u', 'u.id', '=', 'cu.user_id')
            ->where('cu.company_id', $companyId)
            ->when($q !== '', function ($w) use ($like) {
                $w->where(function ($q2) use ($like) {
                    $q2->where('u.email', 'ilike', $like)
                        ->orWhere('u.name', 'ilike', $like);
                });
            })
            ->limit($limit)
            ->get(['u.id', 'u.name', 'u.email', 'cu.role']);
    }

    public function userIdsForCompany(string $companyId): array
    {
        return DB::table('auth.company_user')
            ->where('company_id', $companyId)
            ->pluck('user_id')
            ->all();
    }

    public function upsertMembership(string $companyId, string $userId, string $role, ?string $invitedByUserId = null, $now = null): void
    {
        $now = $now ?: now();
        $exists = $this->verifyMembership($userId, $companyId);

        if ($exists) {
            DB::table('auth.company_user')
                ->where('company_id', $companyId)
                ->where('user_id', $userId)
                ->update([
                    'role' => $role,
                    'invited_by_user_id' => $invitedByUserId,
                    'updated_at' => $now,
                ]);
        } else {
            DB::table('auth.company_user')->insert([
                'company_id' => $companyId,
                'user_id' => $userId,
                'role' => $role,
                'invited_by_user_id' => $invitedByUserId,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }
    }
}

