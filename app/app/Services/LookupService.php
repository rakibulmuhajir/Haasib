<?php

namespace App\Services;

use App\Models\Company;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class LookupService
{
    /**
     * Lookup companies applying membership and query filters.
     */
    public function companies(User $user, string $q = '', int $limit = 10, array $filters = [])
    {
        $query = Company::query()->select(['id','name','slug','base_currency','language','locale']);

        if ($q !== '') {
            $like = '%'.str_replace(['%','_'], ['\\%','\\_'], $q).'%';
            $query->where(function ($w) use ($like) {
                $w->where('name', 'ilike', $like)
                  ->orWhere('slug', 'ilike', $like);
            });
        }

        if (! $user->isSuperAdmin()) {
            $query->whereIn('id', function ($sub) use ($user) {
                $sub->from('auth.company_user')->select('company_id')->where('user_id', $user->id);
            });
        }

        if (($filters['user_id'] ?? null || $filters['user_email'] ?? null) && $user->isSuperAdmin()) {
            $query->whereIn('id', function ($sub) use ($filters) {
                $sub->from('auth.company_user')->select('company_id')
                    ->when($filters['user_id'] ?? null, fn($w, $uid) => $w->where('user_id', $uid))
                    ->when($filters['user_email'] ?? null, function ($w, $email) {
                        $uid = DB::table('users')->where('email', $email)->value('id');
                        if ($uid) {
                            $w->where('user_id', $uid);
                        }
                    });
            });
        }

        return $query->limit($limit)->get();
    }
}
