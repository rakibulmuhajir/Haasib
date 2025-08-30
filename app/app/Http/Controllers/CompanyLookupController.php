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

        $query = Company::query()->select(['id','name']);

        if ($q !== '') {
            $like = '%'.str_replace(['%','_'], ['\\%','\\_'], $q).'%';
            $query->where('name', 'ilike', $like);
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

    public function users(Request $request, string $companyId)
    {
        $user = $request->user();

        // Resolve company by id or slug or name
        $company = Company::where('id', $companyId)
            ->orWhere('slug', $companyId)
            ->orWhere('name', $companyId)
            ->firstOrFail(['id']);

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

