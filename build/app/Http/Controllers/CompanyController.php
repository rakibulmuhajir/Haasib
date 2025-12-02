<?php

namespace App\Http\Controllers;

use App\Facades\CompanyContext;
use App\Http\Requests\CompanyStoreRequest;
use App\Models\Company;
use App\Models\CompanyCurrency;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

class CompanyController extends Controller
{
    public function store(CompanyStoreRequest $request): RedirectResponse
    {
        $data = $request->validated();
        $data['base_currency'] = strtoupper($data['base_currency']);
        $data['slug'] = $data['slug'] ?? Str::slug($data['name']);

        return DB::transaction(function () use ($data) {
            $company = Company::create([
                'name' => $data['name'],
                'industry' => $data['industry'] ?? null,
                'slug' => $data['slug'],
                'country' => $data['country'] ?? null,
                'country_id' => $data['country_id'] ?? null,
                'base_currency' => $data['base_currency'],
                'language' => $data['language'] ?? 'en',
                'locale' => $data['locale'] ?? 'en_US',
                'settings' => $data['settings'] ?? null,
                'created_by_user_id' => Auth::id(),
                'is_active' => true,
            ]);

            CompanyCurrency::updateOrCreate(
                ['company_id' => $company->id, 'currency_code' => $data['base_currency']],
                ['is_base' => true, 'enabled_at' => now()]
            );

            if (Auth::check()) {
                DB::table('auth.company_user')->updateOrInsert(
                    [
                        'company_id' => $company->id,
                        'user_id' => Auth::id(),
                    ],
                    [
                        'role' => 'owner',
                        'invited_by_user_id' => Auth::id(),
                        'joined_at' => now(),
                        'is_active' => true,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]
                );
            }

            return redirect()->route('dashboard')
                ->with('success', 'Company created successfully.');
        });
    }

    public function show(Request $request): Response
    {
        $company = CompanyContext::getCompany();

        // Get user statistics
        $userStats = DB::table('auth.company_user')
            ->where('company_id', $company->id)
            ->selectRaw('
                COUNT(*) as total_users,
                SUM(CASE WHEN is_active = true THEN 1 ELSE 0 END) as active_users,
                SUM(CASE WHEN role = ? THEN 1 ELSE 0 END) as admins
            ', ['admin'])
            ->first();

        // Get all users with details
        $users = DB::table('auth.company_user as cu')
            ->join('auth.users as u', 'cu.user_id', '=', 'u.id')
            ->where('cu.company_id', $company->id)
            ->select(
                'u.id',
                'u.name',
                'u.email',
                'cu.role',
                'cu.is_active',
                'cu.joined_at'
            )
            ->orderBy('cu.joined_at', 'desc')
            ->get();

        // Get current user's role
        $currentUserRole = DB::table('auth.company_user')
            ->where('company_id', $company->id)
            ->where('user_id', Auth::id())
            ->value('role');

        return Inertia::render('company/Show', [
            'company' => [
                'id' => $company->id,
                'name' => $company->name,
                'slug' => $company->slug,
                'base_currency' => $company->base_currency,
                'is_active' => $company->is_active,
                'created_at' => $company->created_at,
                'industry' => $company->industry,
                'country' => $company->country,
            ],
            'stats' => [
                'total_users' => $userStats->total_users ?? 0,
                'active_users' => $userStats->active_users ?? 0,
                'admins' => $userStats->admins ?? 0,
            ],
            'users' => $users,
            'currentUserRole' => $currentUserRole,
        ]);
    }

    public function update(Request $request): JsonResponse
    {
        $data = $request->validate([
            'name' => ['sometimes', 'string', 'max:255'],
            'industry' => ['nullable', 'string', 'max:255'],
            'country' => ['nullable', 'string', 'size:2'],
        ]);

        $company = CompanyContext::getCompany();

        // Check if user is owner or admin
        $membership = DB::table('auth.company_user')
            ->where('company_id', $company->id)
            ->where('user_id', Auth::id())
            ->first();

        if (!$membership || !in_array($membership->role, ['owner', 'admin'])) {
            abort(403, 'Only company owners and admins can update company details.');
        }

        $company->update($data);

        return response()->json([
            'success' => true,
            'message' => 'Company updated successfully.',
            'data' => $company,
        ]);
    }

    public function destroy(string $companyId): JsonResponse
    {
        $company = Company::findOrFail($companyId);

        // Check if user is owner
        $membership = DB::table('auth.company_user')
            ->where('company_id', $company->id)
            ->where('user_id', Auth::id())
            ->first();

        if (!$membership || $membership->role !== 'owner') {
            abort(403, 'Only company owners can delete companies.');
        }

        $result = Bus::dispatch('company.delete', ['id' => $companyId]);

        return response()->json([
            'success' => true,
            'message' => $result['message'] ?? 'Company deleted successfully.',
        ]);
    }
}
