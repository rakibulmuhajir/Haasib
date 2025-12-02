<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    /**
     * Show the dashboard.
     */
    public function index(Request $request): Response
    {
        $user = Auth::user();

        // Get user's companies
        $companies = DB::table('auth.companies as c')
            ->join('auth.company_user as cu', 'c.id', '=', 'cu.company_id')
            ->where('cu.user_id', $user->id)
            ->where('cu.is_active', true)
            ->select(
                'c.id',
                'c.name',
                'c.slug',
                'c.base_currency',
                'c.industry',
                'c.country',
                'c.is_active',
                'cu.role',
                'c.created_at'
            )
            ->orderBy('c.name')
            ->get();

        // Get pending invitations
        $pendingInvitations = DB::table('auth.company_invitations as ci')
            ->join('auth.companies as c', 'ci.company_id', '=', 'c.id')
            ->join('auth.users as u', 'ci.invited_by_user_id', '=', 'u.id')
            ->where('ci.email', $user->email)
            ->where('ci.status', 'pending')
            ->where('ci.expires_at', '>', now())
            ->select(
                'ci.id',
                'ci.token',
                'ci.role',
                'ci.expires_at',
                'ci.created_at',
                'c.id as company_id',
                'c.name as company_name',
                'c.slug as company_slug',
                'u.name as inviter_name',
                'u.email as inviter_email'
            )
            ->orderBy('ci.created_at', 'desc')
            ->get();

        return Inertia::render('Dashboard', [
            'companies' => $companies,
            'pendingInvitations' => $pendingInvitations,
        ]);
    }
}
