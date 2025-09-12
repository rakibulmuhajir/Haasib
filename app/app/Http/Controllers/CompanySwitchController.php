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

        // Ensure session is saved
        Session::save();

        // Debug logging
        \Log::debug('[CompanySwitchController] Stored company ID in session: '.$company->id);
        \Log::debug('[CompanySwitchController] Session after save: ', Session::all());

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
}
