<?php

namespace App\Http\Controllers;

use App\Actions\Company\InviteUser;
use App\Http\Requests\CompanyStoreRequest;
use App\Http\Requests\CompanyInviteRequest;
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
                'language' => $data['language'] ?? 'en',
                'locale' => $data['locale'] ?? 'en-AE',
                'settings' => $data['settings'] ?? [],
            ]);

            // Attach creator as owner
            $user->companies()->attach($company->id, [
                'role' => 'owner',
                'invited_by_user_id' => $user->id,
            ]);
        });

        return response()->json([
            'data' => [
                'id' => $company->id,
                'name' => $company->name,
                'slug' => $company->slug,
                'base_currency' => $company->base_currency,
                'language' => $company->language,
                'locale' => $company->locale,
            ]
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
}

