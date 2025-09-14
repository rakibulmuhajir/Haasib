<?php

namespace App\Actions\DevOps;

use App\Models\Company;
use App\Models\User;
use App\Services\CompanyLookupService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

class CompanyAssign
{
    public function __construct(private CompanyLookupService $lookup) {}

    public function handle(array $p, User $actor): array
    {
        $data = Validator::make($p, [
            'email' => 'required|email',
            'company' => 'required|string',
            'role' => 'required|in:owner,admin,accountant,viewer',
        ])->validate();

        $needle = $data['company'];
        $q = Company::query();
        if (\Illuminate\Support\Str::isUuid($needle)) {
            $q->where('id', $needle);
        } else {
            $q->where(function ($w) use ($needle) {
                $w->where('slug', $needle)->orWhere('name', $needle);
            });
        }
        $company = $q->first();
        if (! $company) {
            throw ValidationException::withMessages(['company' => 'Company not found']);
        }

        if (! $actor->isSuperAdmin()) {
            $active = session('current_company_id');
            abort_if($active !== $company->id, 403);
            abort_unless(
                $this->lookup->userHasRole($company->id, $actor->id, ['owner']),
                403
            );
        }

        $member = DB::transaction(function () use ($data, $company) {
            $user = User::where('email', $data['email'])->first();
            if (! $user) {
                throw ValidationException::withMessages(['email' => 'User not found']);
            }

            // Check if user is already assigned to this company
            if ($company->users()->where('users.id', $user->id)->exists()) {
                throw ValidationException::withMessages(['email' => 'User is already assigned to this company']);
            }

            $company->users()->syncWithoutDetaching([$user->id => ['role' => $data['role']]]);
            $user = $company->users()->where('users.id', $user->id)->first();

            return [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'role' => $user->pivot->role,
            ];
        });

        return ['message' => 'User assigned', 'data' => $member];
    }
}
