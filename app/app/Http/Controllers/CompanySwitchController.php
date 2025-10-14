<?php

namespace App\Http\Controllers;

use App\Models\Company;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Session;

class CompanySwitchController extends Controller
{
    public function switch(Request $request, Company $company)
    {
        $user = $request->user();

        // Check if user is member of the company or is superadmin
        if (! $user->isSuperAdmin()) {
            $isMember = $user->companies()->where('auth.companies.id', $company->id)->exists();

            if (! $isMember) {
                abort(403, 'You are not a member of this company.');
            }
        }

        // Store the company ID in session
        Session::put('current_company_id', $company->id);

        // Clear the global view flag when switching to a company
        Session::forget('super_admin_global_view');

        // Ensure session is saved
        Session::save();

        // Verify session was saved
        $savedCompanyId = Session::get('current_company_id');

        // Debug logging
        \Log::debug('[CompanySwitchController] Attempting to store company ID: '.$company->id);
        \Log::debug('[CompanySwitchController] Retrieved from session after save: '.$savedCompanyId);
        \Log::debug('[CompanySwitchController] Session after save: ', Session::all());
        \Log::debug('[CompanySwitchController] Session ID: '.Session::getId());

        // If the request expects JSON, return success response
        if ($request->expectsJson()) {
            return response()->json([
                'message' => 'Company switched successfully',
                'company_id' => $company->id,
                'company_name' => $company->name,
            ]);
        }

        // Otherwise redirect back or to dashboard
        return redirect()->intended(route('dashboard'))
            ->with('success', "Switched to {$company->name}");
    }

    public function setFirstCompany(Request $request)
    {
        $user = $request->user();
        $firstCompany = $user->companies()->first();

        if (! $firstCompany) {
            return redirect()->route('dashboard')
                ->with('error', 'You are not a member of any company.');
        }

        Session::put('current_company_id', $firstCompany->id);

        return redirect()->route('dashboard')
            ->with('success', "Welcome! You're now logged into {$firstCompany->name}.");
    }

    public function clearContext(Request $request)
    {
        $user = $request->user();

        // Only allow super admins to clear company context
        if (! $user->isSuperAdmin()) {
            abort(403, 'Unauthorized');
        }

        // Remove company ID from session
        Session::forget('current_company_id');

        // Set a flag to indicate super admin intentionally cleared company context
        Session::put('super_admin_global_view', true);

        Session::save();

        \Log::debug('[CompanySwitchController] Cleared company context for super admin');
        \Log::debug('[CompanySwitchController] Set global view flag for super admin');

        // If the request expects JSON, return success response
        if ($request->expectsJson()) {
            return response()->json([
                'message' => 'Company context cleared successfully',
                'company_id' => null,
            ]);
        }

        return redirect()->route('dashboard')
            ->with('success', 'Now operating in Global View mode');
    }
}
