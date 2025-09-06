<?php

namespace App\Services;


use App\Models\Company;
use App\Models\User;
use App\Services\CompanyLookupService;
use Illuminate\Support\Facades\DB;

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

    /**
     * Generic suggest helper for small reference tables (languages, currencies, locales, countries).
     * Controllers supply table name, selectable columns, searchable columns, and optional filter mapping.
     */
    public function suggest(string $table, array $options = [], array $filterMap = [])
    {
        $select = $options['select'] ?? ['*'];
        $searchCols = $options['search'] ?? [];
        $orderBy = $options['order'] ?? null;

        $q = (string) request()->query('q', '');
        $limit = (int) request()->query('limit', 10);

        $query = DB::table($table)->select($select);

        if ($q !== '' && !empty($searchCols)) {
            $like = '%'.str_replace(['%','_'], ['\\%','\\_'], $q).'%';
            $query->where(function ($w) use ($searchCols, $like) {
                foreach ($searchCols as $i => $col) {
                    if ($i === 0) $w->where($col, 'ilike', $like);
                    else $w->orWhere($col, 'ilike', $like);
                }
            });
        }

        // Apply mapped filters from query string
        foreach ($filterMap as $reqKey => $map) {
            $val = request()->query($reqKey);
            if ($val === null || $val === '') continue;
            if (is_string($map)) {
                $query->where($map, $val);
            } elseif (is_array($map)) {
                $col = $map['column'] ?? $reqKey;
                $type = $map['type'] ?? null;
                if ($type === 'bool') {
                    $truthy = ['1','true','yes','on'];
                    $val = is_bool($val) ? $val : in_array(strtolower((string) $val), $truthy, true);
                }
                $query->where($col, $val);
            }
        }

        if ($orderBy) {
            $query->orderBy($orderBy);
        }

        return $query->limit($limit)->get();
    }
}
