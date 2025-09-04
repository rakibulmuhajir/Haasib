<?php

namespace App\Services;

use App\Models\Company;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class CompanyLookupService
{
    public function resolveById(string $id): ?Company
    {
        return Company::where('id', $id)->first();
    }

    public function resolveBySlug(string $slug): ?Company
    {
        return Company::where('slug', $slug)->first();
    }

    public function resolveByName(string $name): ?Company
    {
        return Company::where('name', $name)->first();
    }

    public function resolve(string $value): Company
    {
        $query = Company::query();
        if (Str::isUuid($value)) {
            $query->where('id', $value);
        } else {
            $query->where(function ($w) use ($value) {
                $w->where('slug', $value)->orWhere('name', $value);
            });
        }
        return $query->firstOrFail(['id','name','slug','base_currency','language','locale','created_at','updated_at']);
    }

    public function isMember(string $companyId, string $userId): bool
    {
        return DB::table('auth.company_user')
            ->where('company_id', $companyId)
            ->where('user_id', $userId)
            ->exists();
    }

    public function userRole(string $companyId, string $userId): ?string
    {
        return DB::table('auth.company_user')
            ->where('company_id', $companyId)
            ->where('user_id', $userId)
            ->value('role');
    }

    public function userHasRole(string $companyId, string $userId, array $roles): bool
    {
        return DB::table('auth.company_user')
            ->where('company_id', $companyId)
            ->where('user_id', $userId)
            ->whereIn('role', $roles)
            ->exists();
    }

    public function members(string $companyId, int $limit = 10, string $q = '')
    {
        $like = '%'.str_replace(['%','_'], ['\\%','\\_'], $q).'%';
        return DB::table('auth.company_user as cu')
            ->join('users as u', 'u.id', '=', 'cu.user_id')
            ->where('cu.company_id', $companyId)
            ->when($q !== '', function ($w) use ($like) {
                $w->where(function($q2) use ($like) {
                    $q2->where('u.email', 'ilike', $like)
                       ->orWhere('u.name', 'ilike', $like);
                });
            })
            ->select('u.id','u.name','u.email','cu.role')
            ->orderBy('u.name')
            ->limit($limit)
            ->get();
    }

    public function owners(string $companyId)
    {
        return DB::table('auth.company_user as cu')
            ->join('users as u', 'u.id', '=', 'cu.user_id')
            ->where('cu.company_id', $companyId)
            ->where('cu.role', 'owner')
            ->select('u.id','u.name','u.email')
            ->orderBy('u.name')
            ->get();
    }

    public function membersCount(string $companyId): int
    {
        return DB::table('auth.company_user')
            ->where('company_id', $companyId)
            ->count();
    }

    public function roleCounts(string $companyId)
    {
        return DB::table('auth.company_user')
            ->select('role', DB::raw('count(*) as cnt'))
            ->where('company_id', $companyId)
            ->groupBy('role')
            ->pluck('cnt','role');
    }

    public function memberUserIds(string $companyId)
    {
        return DB::table('auth.company_user')
            ->where('company_id', $companyId)
            ->pluck('user_id');
    }

    public function membershipsForUser(string $userId)
    {
        return DB::table('auth.company_user as cu')
            ->join('auth.companies as c', 'c.id', '=', 'cu.company_id')
            ->where('cu.user_id', $userId)
            ->orderBy('c.name')
            ->get(['c.id','c.name','c.slug','cu.role']);
    }

    public function shareCompany(string $userId, string $otherUserId): bool
    {
        return DB::table('auth.company_user as cu1')
            ->join('auth.company_user as cu2', function($j) use ($otherUserId) {
                $j->on('cu1.company_id', '=', 'cu2.company_id')
                  ->where('cu2.user_id', '=', $otherUserId);
            })
            ->where('cu1.user_id', $userId)
            ->exists();
    }

    public function restrictUsersToCompany(Builder $query, string $companyId): void
    {
        $query->whereIn('id', function ($sub) use ($companyId) {
            $sub->from('auth.company_user')->select('user_id')->where('company_id', $companyId);
        });
    }

    public function restrictCompaniesToUser(Builder $query, string $userId): void
    {
        $query->whereIn('id', function ($sub) use ($userId) {
            $sub->from('auth.company_user')->select('company_id')->where('user_id', $userId);
        });
    }

    public function upsertMember(string $companyId, string $userId, array $data): void
    {
        $now = $data['updated_at'] ?? now();
        $exists = DB::table('auth.company_user')
            ->where('company_id', $companyId)
            ->where('user_id', $userId)
            ->exists();

        if ($exists) {
            DB::table('auth.company_user')
                ->where('company_id', $companyId)
                ->where('user_id', $userId)
                ->update(array_merge($data, ['updated_at' => $now]));
        } else {
            DB::table('auth.company_user')->insert(array_merge([
                'company_id' => $companyId,
                'user_id' => $userId,
                'created_at' => $now,
                'updated_at' => $now,
            ], $data));
        }
    }
}
