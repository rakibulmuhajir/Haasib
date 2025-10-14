<?php

namespace App\Http\Controllers;

use App\Actions\Company\InviteUser;
use App\Http\Requests\CompanyInviteRequest;
use App\Http\Requests\CompanyStoreRequest;
use App\Models\Company;
use Illuminate\Support\Facades\DB;

class CompanyController extends Controller
{
    public function store(CompanyStoreRequest $request)
    {
        $user = $request->user();
        $data = $request->validated();

        $company = null;
        DB::transaction(function () use ($data, $user, &$company) {
            $company = Company::create([
                'name' => $data['name'],
                'base_currency' => $data['base_currency'] ?? 'AED',
                'language' => 'en',
                'locale' => 'en-US',
                'settings' => $data['settings'] ?? [],
                'created_by_user_id' => $user->id,
            ]);

            // Attach creator as owner
            $user->companies()->attach($company->id, [
                'role' => 'owner',
                'invited_by_user_id' => $user->id,
            ]);

            // Set currency_id based on base_currency
            if (isset($data['base_currency'])) {
                $currency = \App\Models\Currency::where('code', $data['base_currency'])->first();
                if ($currency) {
                    $company->currency_id = $currency->id;
                    $company->save();
                }
            }
        });

        return response()->json([
            'data' => [
                'id' => $company->id,
                'name' => $company->name,
                'slug' => $company->slug,
                'base_currency' => $company->base_currency,
                'language' => $company->language,
                'locale' => $company->locale,
            ],
        ], 201);
    }

    public function invite(CompanyInviteRequest $request, string $company)
    {
        $data = $request->validated();
        $result = app(InviteUser::class)->handle($company, $data, $request->user());

        return response()->json([
            'data' => $result,
        ], 201);
    }

    public function activate(string $company)
    {
        $user = request()->user();
        abort_unless($user->isSuperAdmin(), 403);

        // Try to find by slug first, then by UUID
        $companyModel = Company::where('slug', $company)->first();

        if (! $companyModel) {
            // If not found by slug, try by UUID
            $companyModel = Company::where('id', $company)->firstOrFail();
        }

        $companyModel->activate();

        return response()->json([
            'message' => 'Company activated successfully',
        ]);
    }

    public function deactivate(string $company)
    {
        $user = request()->user();
        abort_unless($user->isSuperAdmin(), 403);

        // Try to find by slug first, then by UUID
        $companyModel = Company::where('slug', $company)->first();

        if (! $companyModel) {
            // If not found by slug, try by UUID
            $companyModel = Company::where('id', $company)->firstOrFail();
        }

        $companyModel->deactivate();

        return response()->json([
            'message' => 'Company deactivated successfully',
        ]);
    }

    public function destroy(string $company)
    {
        $user = request()->user();
        abort_unless($user->isSuperAdmin(), 403);

        // Try to find by slug first, then by UUID
        $companyModel = Company::where('slug', $company)->first();

        if (! $companyModel) {
            // If not found by slug, try by UUID
            $companyModel = Company::where('id', $company)->firstOrFail();
        }

        $companyModel->delete();

        return response()->json([
            'message' => 'Company deleted successfully',
        ]);
    }
}
