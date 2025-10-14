<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Services\CompanyLookupService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class UserLookupController extends Controller
{
    public function __construct(protected CompanyLookupService $lookup) {}

    public function suggest(Request $request)
    {
        $user = $request->user();

        $q = (string) $request->query('q', '');
        $companyId = $request->query('company_id');
        $limit = (int) $request->query('limit', 8);

        $query = User::query();

        if ($q !== '') {
            $like = '%'.str_replace(['%', '_'], ['\\%', '\\_'], $q).'%';
            $query->where(function ($w) use ($like) {
                $w->where('email', 'ilike', $like)
                    ->orWhere('name', 'ilike', $like);
            });
        }

        if (! $user->isSuperAdmin()) {
            // Non-superadmin: restrict to users in the current company (or provided company_id)
            $cid = $companyId ?: $request->session()->get('current_company_id');
            abort_if(! $cid, 422, 'Company context required');
            $this->lookup->restrictUsersToCompany($query, $cid);
        }

        $users = $query->limit($limit)->get(['id', 'name', 'email', 'is_active', 'system_role']);

        return response()->json(['data' => $users]);
    }

    public function show(Request $request, string $userKey)
    {
        $actor = $request->user();
        $user = User::query()
            ->when(str_contains($userKey, '@'), fn ($q) => $q->where('email', $userKey), fn ($q) => $q->where('id', $userKey))
            ->firstOrFail(['id', 'name', 'email', 'is_active', 'system_role', 'created_at', 'updated_at']);

        // Access: superadmin OR share at least one company
        if (! $actor->isSuperAdmin()) {
            $shared = $this->lookup->shareCompany($user->id, $actor->id);
            abort_unless($shared, 403);
        }

        // Memberships
        $memberships = $this->lookup->membershipsForUser($user->id);

        // Last activity from audit logs if available
        $lastActivity = null;
        try {
            $lastActivity = DB::table('audit_logs')
                ->where('user_id', $user->id)
                ->orderByDesc('created_at')
                ->limit(1)
                ->first(['action', 'company_id', 'created_at']);
        } catch (\Throwable $e) { /* ignore */
        }

        return response()->json([
            'data' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'is_active' => $user->is_active,
                'system_role' => $user->system_role,
                'memberships' => $memberships,
                'last_activity' => $lastActivity,
            ],
        ]);
    }
}
