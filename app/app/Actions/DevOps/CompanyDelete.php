<?php

namespace App\Actions\DevOps;

use App\Models\Company;
use App\Models\User;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\DB;

class CompanyDelete
{
    public function handle(array $p, User $actor): array
    {
        abort_unless($actor->isSuperAdmin(), 403);

        $data = Validator::make($p, [
            'company' => 'required|string',
        ])->validate();

        DB::transaction(function () use ($data) {
            $company = Company::where('id', $data['company'])
                ->orWhere('slug', $data['company'])
                ->orWhere('name', $data['company'])
                ->firstOrFail();
            $company->delete();
        });

        return ['message' => 'Company deleted', 'data' => ['company' => $data['company']]];
    }
}
