<?php

namespace App\Http\Controllers;

use App\Models\Company;
use App\Models\User;
use App\Services\CompanyLookupService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class CompanyLookupController extends Controller
{
    public function __construct(protected CompanyLookupService $lookup) {}

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
            $this->lookup->restrictCompaniesToUser($query, $user->id);
        }

        // Filter: companies associated to a specific user (superadmin only)
        if (($userId || $userEmail) && $user->isSuperAdmin()) {
            if (! $userId && $userEmail) {
                $userId = User::where('email', $userEmail)->value('id');
            }
            if ($userId) {
                $this->lookup->restrictCompaniesToUser($query, $userId);
            }
        }

        $companies = $query->limit($limit)->get();
        return response()->json(['data' => $companies]);
    }

    public function show(Request $request, string $company)
    {
        $user = $request->user();
        $record = $this->lookup->resolve($company);

        if (! $user->isSuperAdmin()) {
            abort_unless($this->lookup->isMember($record->id, $user->id), 403);
        }

        $members = $this->lookup->members($record->id, 10);

        $owners = $this->lookup->owners($record->id);

        $roleCounts = $this->lookup->roleCounts($record->id);

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
                'members_count' => $this->lookup->membersCount($record->id),
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
        $company = $this->lookup->resolve($companyId);

        // Access: superadmin OR any member of the company
        if (! $user->isSuperAdmin()) {
            abort_unless($this->lookup->isMember($company->id, $user->id), 403);
        }

        $q = (string) $request->query('q', '');
        $limit = (int) $request->query('limit', 10);
        $users = $this->lookup->members($company->id, $limit, $q);

        return response()->json(['data' => $users]);
    }
}

