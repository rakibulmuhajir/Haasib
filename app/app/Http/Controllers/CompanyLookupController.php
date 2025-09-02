<?php

namespace App\Http\Controllers;

use App\Models\Company;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class CompanyLookupController extends Controller
{
    public function index(Request $request)
    {
        $user = $request->user();
        $q = (string) $request->query('q', '');
        $userId = $request->query('user_id');
        $userEmail = $request->query('user_email');
        $limit = (int) $request->query('limit', 10);

        $query = Company::query()->select(['id','name','slug','base_currency','language','locale']);

        if ($q !== '') {
            $like = '%'.str_replace(['%','_'], ['\\%','\\_'], $q).'%';
            $query->where(function($w) use ($like) {
                $w->where('name', 'ilike', $like)
                  ->orWhere('slug', 'ilike', $like);
            });
        }

        // If superadmin, can see all; otherwise limit to companies the current user belongs to
        if (! $user->isSuperAdmin()) {
            $query->whereIn('id', function ($sub) use ($user) {
                $sub->from('auth.company_user')->select('company_id')->where('user_id', $user->id);
            });
        }

        // Filter: companies associated to a specific user (superadmin only)
        if (($userId || $userEmail) && $user->isSuperAdmin()) {
            $query->whereIn('id', function ($sub) use ($userId, $userEmail) {
                $sub->from('auth.company_user')->select('company_id')
                    ->when($userId, fn($w) => $w->where('user_id', $userId))
                    ->when($userEmail, function ($w) use ($userEmail) {
                        $uid = User::where('email', $userEmail)->value('id');
                        if ($uid) $w->where('user_id', $uid);
                    });
            });
        }

        $companies = $query->limit($limit)->get();
        return response()->json(['data' => $companies]);
    }

    public function show(Request $request, string $company)
    {
        $user = $request->user();
        $query = Company::query();
        if (\Illuminate\Support\Str::isUuid($company)) {
            $query->where('id', $company);
        } else {
            $query->where(function ($w) use ($company) {
                $w->where('slug', $company)->orWhere('name', $company);
            });
        }
        $record = $query->firstOrFail(['id','name','slug','base_currency','language','locale','created_at','updated_at']);

        if (! $user->isSuperAdmin()) {
            $isMember = DB::table('auth.company_user')
                ->where('user_id', $user->id)
                ->where('company_id', $record->id)
                ->exists();
            abort_unless($isMember, 403);
        }

        $members = DB::table('auth.company_user as cu')
            ->join('users as u', 'u.id', '=', 'cu.user_id')
            ->where('cu.company_id', $record->id)
            ->select('u.id','u.name','u.email','cu.role')
            ->orderBy('u.name')
            ->limit(10)
            ->get();

        $owners = DB::table('auth.company_user as cu')
            ->join('users as u', 'u.id', '=', 'cu.user_id')
            ->where('cu.company_id', $record->id)
            ->where('cu.role', 'owner')
            ->select('u.id','u.name','u.email')
            ->orderBy('u.name')
            ->get();

        $roleCounts = DB::table('auth.company_user')
            ->select('role', DB::raw('count(*) as cnt'))
            ->where('company_id', $record->id)
            ->groupBy('role')
            ->pluck('cnt','role');

        // Latest activity from audit logs if available
        $lastActivity = null;
        try {
            $lastActivity = DB::table('audit.audit_logs')
                ->where('company_id', $record->id)
                ->orderByDesc('created_at')
                ->limit(1)
                ->first(['action','created_at']);
        } catch (\Throwable $e) {
            // audit schema may not exist yet; ignore
        }

        return response()->json([
            'data' => [
                'id' => $record->id,
                'name' => $record->name,
                'slug' => $record->slug,
                'base_currency' => $record->base_currency,
                'language' => $record->language,
                'locale' => $record->locale,
                'members_preview' => $members,
                'members_count' => DB::table('auth.company_user')->where('company_id', $record->id)->count(),
                'owners' => $owners,
                'role_counts' => (object) $roleCounts,
                'last_activity' => $lastActivity,
            ]
        ]);
    }

    public function users(Request $request, string $companyId)
    {
        $user = $request->user();

        // Resolve company by id or slug or name
        $q = Company::query();
        if (\Illuminate\Support\Str::isUuid($companyId)) {
            $q->where('id', $companyId);
        } else {
            $q->where(function ($w) use ($companyId) {
                $w->where('slug', $companyId)->orWhere('name', $companyId);
            });
        }
        $company = $q->firstOrFail(['id']);

        // Access: superadmin OR any member of the company
        if (! $user->isSuperAdmin()) {
            $isMember = DB::table('auth.company_user')
                ->where('user_id', $user->id)
                ->where('company_id', $company->id)
                ->exists();
            abort_unless($isMember, 403);
        }

        $q = (string) $request->query('q', '');
        $limit = (int) $request->query('limit', 10);

        $like = '%'.str_replace(['%','_'], ['\\%','\\_'], $q).'%';
        $users = DB::table('auth.company_user as cu')
            ->join('users as u', 'u.id', '=', 'cu.user_id')
            ->where('cu.company_id', $company->id)
            ->when($q !== '', function ($w) use ($like) {
                $w->where(function($q2) use ($like) {
                    $q2->where('u.email', 'ilike', $like)
                       ->orWhere('u.name', 'ilike', $like);
                });
            })
            ->limit($limit)
            ->get(['u.id','u.name','u.email','cu.role']);

        return response()->json(['data' => $users]);
    }
}
