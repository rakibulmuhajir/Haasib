<?php

namespace App\Support;

use App\Models\User;
use Illuminate\Support\Facades\Auth;
use App\Support\CommandBus;

class DevOpsService
{
    protected function actor(): User
    {
        return Auth::user() ?? User::where('system_role', 'superadmin')->firstOrFail();
    }

    public function createUser(string $name, string $email, ?string $password = null): array
    {
        return CommandBus::dispatch('user.create', [
            'name' => $name,
            'email' => $email,
            'password' => $password,
        ], $this->actor());
    }

    public function deleteUser(string $email): array
    {
        return CommandBus::dispatch('user.delete', ['email' => $email], $this->actor());
    }

    public function createCompany(string $name): array
    {
        return CommandBus::dispatch('company.create', ['name' => $name], $this->actor());
    }

    public function deleteCompany(string $company): array
    {
        return CommandBus::dispatch('company.delete', ['company' => $company], $this->actor());
    }

    public function assignCompany(string $email, string $company, string $role = 'viewer'): array
    {
        return CommandBus::dispatch('company.assign', [
            'email' => $email,
            'company' => $company,
            'role' => $role,
        ], $this->actor());
    }

    public function unassignCompany(string $email, string $company): array
    {
        return CommandBus::dispatch('company.unassign', [
            'email' => $email,
            'company' => $company,
        ], $this->actor());
    }

    public function createUserAndCompanies(string $name, string $email, array $companies, string $role = 'owner'): array
    {
        $summary = $this->createUser($name, $email);
        foreach ($companies as $co) {
            $this->createCompany($co);
            $this->assignCompany($email, $co, $role);
        }
        return ['summary' => $summary, 'companies' => $companies];
    }
}

