<?php

namespace Modules\Accounting\Domain\Actions;

use App\Models\Company;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class ExampleAction
{
    /**
     * Execute the action.
     */
    public function execute(Company $company, array $data, User $user): mixed
    {
        // Example action implementation
        return DB::transaction(function () use ($company, $data, $user) {
            // Your action logic here
            return $data;
        });
    }
}
