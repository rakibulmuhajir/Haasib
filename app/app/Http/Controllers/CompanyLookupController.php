<?php

namespace App\Http\Controllers;

use App\Models\Company;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\Repositories\CompanyMembershipRepository;

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

        $repo = app(CompanyMembershipRepository::class);

        // If superadmin, can see all; otherwise limit to companies the current user belongs to
        if (! $user->isSuperAdmin()) {
            $ids = $repo->memberships($user->id)->pluck('id');
            $query->whereIn('id', $ids);
        }

        // Filter: companies associated to a specific user (superadmin only)
        if (($userId || $userEmail) && $user->isSuperAdmin()) {
            $targetId = $userId;
            if (! $targetId && $userEmail) {
                $targetId = User::where('email', $userEmail)->value('id');
            }
            if ($targetId) {
                $ids = $repo->memberships($targetId)->pluck('id');
                $query->whereIn('id', $ids);
            } else {
                $query->whereRaw('1 = 0');
            }
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

        $repo = app(CompanyMembershipRepository::class);

        if (! $user->isSuperAdmin()) {
            abort_unless($repo->verifyMembership($user->id, $record->id), 403);
        }

        $members = $repo->membersPreview($record->id);
        $owners = $repo->owners($record->id);
        $roleCounts = $repo->roleCounts($record->id);
        $membersCount = $repo->countMembers($record->id);

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
                'members_count' => $membersCount,
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

        $repo = app(CompanyMembershipRepository::class);

        // Access: superadmin OR any member of the company
        if (! $user->isSuperAdmin()) {
            abort_unless($repo->verifyMembership($user->id, $company->id), 403);
        }

        $qTerm = (string) $request->query('q', '');
        $limit = (int) $request->query('limit', 10);

        $users = $repo->searchMembers($company->id, $qTerm, $limit);

        return response()->json(['data' => $users]);
    }
}

