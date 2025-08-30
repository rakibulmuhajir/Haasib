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
}

