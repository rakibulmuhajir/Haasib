<?php

namespace App\Services;


use App\Models\Company;
use App\Models\User;
use App\Services\CompanyLookupService;

class LookupService
{
    public function __construct(protected CompanyLookupService $companies) {}

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
            $this->companies->restrictCompaniesToUser($query, $user->id);
        }

        if (($filters['user_id'] ?? null || $filters['user_email'] ?? null) && $user->isSuperAdmin()) {
            $this->companies->restrictCompaniesToUserIdOrEmail($query, $filters['user_id'] ?? null, $filters['user_email'] ?? null);
        }

        return $query->limit($limit)->get();
    }
}
