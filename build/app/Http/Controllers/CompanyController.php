<?php

namespace App\Http\Controllers;

use App\Http\Requests\CompanyStoreRequest;
use App\Models\Company;
use App\Models\CompanyCurrency;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

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
}
