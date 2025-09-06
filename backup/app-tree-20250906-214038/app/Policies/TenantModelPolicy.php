<?php

// app/Policies/TenantModelPolicy.php
namespace App\Policies;
use App\Models\User;
use App\Support\Tenancy;

abstract class TenantModelPolicy {
    public function __construct(protected Tenancy $tenancy) {}

    protected function sameCompany(User $user, $model): bool {
        $cid = $this->tenancy->currentCompanyId();
        return $cid && (data_get($model,'company_id') === $cid);
    }
    public function view(User $user, $model): bool { return $this->sameCompany($user,$model); }
    public function update(User $user, $model): bool { return $this->sameCompany($user,$model); }
    public function delete(User $user, $model): bool { return $this->sameCompany($user,$model); }
}
