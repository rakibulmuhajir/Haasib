<?php

namespace App\Actions\DevOps;

use App\Models\Company;
use App\Models\User;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\DB;

class CompanyCreate
{
    public function handle(array $p, User $actor): array
    {
        abort_unless($actor->isSuperAdmin(), 403);

        $data = Validator::make($p, [
            'name' => 'required|string|unique:auth.companies,name',
            'base_currency' => 'nullable|string|size:3',
            'language' => 'nullable|string',
            'locale' => 'nullable|string',
        ])->validate();

        $company = DB::transaction(fn() => Company::create($data));

        return ['message' => 'Company created', 'data' => ['slug' => $company->slug]];
    }
}
