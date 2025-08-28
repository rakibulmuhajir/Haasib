<?php

namespace App\Actions\DevOps;

use App\Models\Company;
use App\Models\User;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\DB;

class CompanyAssign
{
    public function handle(array $p, User $actor): array
    {
        $data = Validator::make($p, [
            'email' => 'required|email',
            'company' => 'required|string',
            'role' => 'required|in:owner,admin,accountant,viewer',
        ])->validate();

        $company = Company::where('id', $data['company'])
            ->orWhere('slug', $data['company'])
            ->orWhere('name', $data['company'])
            ->firstOrFail();

        if (!$actor->isSuperAdmin()) {
            $active = session('current_company_id');
            abort_if($active !== $company->id, 403);
            $isOwner = $actor->companies()
                ->where('auth.company_user.company_id', $company->id)
                ->wherePivot('role', 'owner')
                ->exists();
            abort_unless($isOwner, 403);
        }

        DB::transaction(function () use ($data, $company) {
            $user = User::where('email', $data['email'])->firstOrFail();
            $company->users()->syncWithoutDetaching([$user->id => ['role' => $data['role']]]);
        });

        return ['message' => 'User assigned', 'data' => ['email' => $data['email'], 'company' => $company->slug]];
    }
}
