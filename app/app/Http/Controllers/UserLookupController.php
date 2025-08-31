<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;

class UserLookupController extends Controller
{
    public function suggest(Request $request)
    {
        $user = $request->user();

        $q = (string) $request->query('q', '');
        $companyId = $request->query('company_id');
        $limit = (int) $request->query('limit', 8);

        $query = User::query();

        if ($q !== '') {
            $like = '%'.str_replace(['%','_'], ['\\%','\\_'], $q).'%';
            $query->where(function ($w) use ($like) {
                $w->where('email', 'ilike', $like)
                  ->orWhere('name', 'ilike', $like);
            });
        }

        if (! $user->isSuperAdmin()) {
            // Non-superadmin: restrict to users in the current company (or provided company_id)
            $cid = $companyId ?: $request->session()->get('current_company_id');
            abort_if(! $cid, 422, 'Company context required');
            $query->whereIn('id', function ($sub) use ($cid) {
                $sub->from('auth.company_user')->select('user_id')->where('company_id', $cid);
            });
        }

        $users = $query->limit($limit)->get(['id','name','email']);
        return response()->json(['data' => $users]);
    }

    public function show(Request $request, string $userKey)
    {
        $actor = $request->user();
        $user = User::query()
            ->when(str_contains($userKey, '@'), fn($q) => $q->where('email', $userKey), fn($q) => $q->where('id', $userKey))
            ->firstOrFail(['id','name','email','created_at','updated_at']);

        // Access: superadmin OR share at least one company
        if (! $actor->isSuperAdmin()) {
            $shared = \Illuminate\Support\Facades\DB::table('auth.company_user as cu1')
                ->join('auth.company_user as cu2', function($j) use ($actor) {
                    $j->on('cu1.company_id', '=', 'cu2.company_id')
                      ->where('cu2.user_id', '=', $actor->id);
                })
                ->where('cu1.user_id', $user->id)
                ->exists();
            abort_unless($shared, 403);
        }

        // Memberships
        $memberships = \Illuminate\Support\Facades\DB::table('auth.company_user as cu')
            ->join('auth.companies as c', 'c.id', '=', 'cu.company_id')
            ->where('cu.user_id', $user->id)
            ->orderBy('c.name')
            ->get(['c.id','c.name','c.slug','cu.role']);

        // Last activity from audit logs if available
        $lastActivity = null;
        try {
            $lastActivity = \Illuminate\Support\Facades\DB::table('audit.audit_logs')
                ->where('user_id', $user->id)
                ->orderByDesc('created_at')
                ->limit(1)
                ->first(['action','company_id','created_at']);
        } catch (\Throwable $e) { /* ignore */ }

        return response()->json([
            'data' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'memberships' => $memberships,
                'last_activity' => $lastActivity,
            ]
        ]);
    }
}
