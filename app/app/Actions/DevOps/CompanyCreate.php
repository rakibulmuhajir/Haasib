<?php

namespace App\Actions\DevOps;

use App\Models\Company;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

class CompanyCreate
{
    public function handle(array $p, User $actor): array
    {
        abort_unless($actor->isSuperAdmin(), 403);

        $data = Validator::make($p, [
            'name' => ['required', 'string', Rule::unique(Company::class, 'name')],
            'base_currency' => 'required|string|size:3|exists:currencies,code',
            'country' => 'required|string|size:2|exists:countries,code',
            'language' => 'nullable|string|exists:languages,code',
            'locale' => 'nullable|string|exists:locales,code',
        ])->validate();

        $company = DB::transaction(function () use ($data, $actor) {
            // Create the company with creator information and defaults
            $company = Company::create(array_merge($data, [
                'created_by_user_id' => $actor->id,
                'language' => $data['language'] ?? 'en',
                'locale' => $data['locale'] ?? 'en-US',
            ]));

            // Set currency_id based on base_currency
            $currency = \App\Models\Currency::where('code', $data['base_currency'])->first();
            if ($currency) {
                $company->currency_id = $currency->id;
                $company->save();
            }

            // Set country_id based on country
            $country = DB::table('countries')->where('code', $data['country'])->first();
            if ($country) {
                $company->country_id = $country->id;
                $company->save();
            }

            // Automatically assign the creator as the owner of the company
            $company->users()->attach($actor->id, [
                'role' => 'owner',
                'invited_by_user_id' => $actor->id,
            ]);

            return $company;
        });

        return ['message' => 'Company created', 'data' => ['slug' => $company->slug]];
    }
}
