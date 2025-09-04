<?php

namespace App\Actions\DevOps;

use App\Models\Company;
use App\Models\User;
use App\Services\CompanyLookupService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class CompanyUnassign
{
    public function __construct(private CompanyLookupService $lookup)
    {
    }

    public function handle(array $p, User $actor): array
    {
        $data = Validator::make($p, [
            'email' => 'required|email',
            'company' => 'required|string',
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
        $company = $q->firstOrFail();

        if (!$actor->isSuperAdmin()) {
            $active = session('current_company_id');
            abort_if($active !== $company->id, 403);
            abort_unless(
                $this->lookup->userHasRole($company->id, $actor->id, ['owner']),
                403
            );
        }

        DB::transaction(function () use ($data, $company) {
            $user = User::where('email', $data['email'])->firstOrFail();
            $company->users()->detach($user->id);
        });

        return ['message' => 'User unassigned', 'data' => ['email' => $data['email'], 'company' => $company->slug]];
    }
}
