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
            'base_currency' => 'nullable|string|size:3',
            'language' => 'nullable|string',
            'locale' => 'nullable|string',
        ])->validate();

        $company = DB::transaction(function () use ($data, $actor) {
            // Create the company with creator information
            $company = Company::create(array_merge($data, [
                'created_by_user_id' => $actor->id,
            ]));

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
