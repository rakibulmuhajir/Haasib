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
            $q = Company::query();
            $needle = $data['company'];
            // Avoid invalid UUID casts on Postgres by only querying id when it looks like a UUID
            if (\Illuminate\Support\Str::isUuid($needle)) {
                $q->where('id', $needle);
            } else {
                $q->where(function ($sub) use ($needle) {
                    $sub->where('slug', $needle)->orWhere('name', $needle);
                });
            }
            $company = $q->firstOrFail();
            $company->delete();
        });

        return ['message' => 'Company deleted', 'data' => ['company' => $data['company']]];
    }
}
