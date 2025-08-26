<?php
// app/Support/DevOpsService.php
namespace App\Support;

use App\Models\User;
use App\Models\Company;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class DevOpsService
{
    public function createUser(string $name, string $email, ?string $password = null): array
    {
        $email = strtolower(trim($email));
        if ($email === '') throw ValidationException::withMessages(['email' => 'Email required']);
        $user = User::firstOrCreate(
            ['email' => $email],
            ['name' => $name ?: 'User', 'password' => Hash::make($password ?: Str::password(12))]
        );
        return ['user' => $user->only(['id','name','email'])];
    }

    public function createCompany(string $name): array
    {
        $name = trim($name);
        if ($name === '') throw \Illuminate\Validation\ValidationException::withMessages(['name'=>'Company name required']);
        $slug = \Illuminate\Support\Str::slug($name) ?: \Illuminate\Support\Str::slug(\Illuminate\Support\Str::uuid());

        $company = \App\Models\Company::where('slug', $slug)->orWhere('name', $name)->first();
        if (!$company) $company = \App\Models\Company::create(['name' => $name, 'slug' => $slug]);

        return ['company' => $company->only(['id','name','slug'])];
    }

    public function assignCompany(string $email, string $company, string $role = 'viewer'): array
    {
        $email = strtolower(trim($email));
        $user = User::where('email', $email)->firstOrFail();
        $c = $this->findCompany($company);
        $user->companies()->syncWithoutDetaching([$c->id => ['role' => $role]]);
        return ['user' => $user->only(['id','email']), 'company' => $c->only(['id','name']), 'role' => $role];
    }

    public function unassignCompany(string $email, string $company): array
    {
        $user = User::where('email', strtolower(trim($email)))->firstOrFail();
        $c = $this->findCompany($company);
        $user->companies()->detach($c->id);
        return ['user' => $user->only(['id','email']), 'company' => $c->only(['id','name'])];
    }

    public function deleteUser(string $email): array
    {
        $user = User::where('email', strtolower(trim($email)))->firstOrFail();
        $user->delete();
        return ['deleted_user' => $email];
    }

    public function deleteCompany(string $company): array
    {
        $c = $this->findCompany($company);
        $c->delete();
        return ['deleted_company' => $c->only(['id','name'])];
    }

    public function createUserAndCompanies(string $name, string $email, array $companies, string $role = 'owner'): array
    {
        $out = $this->createUser($name, $email);
        foreach ($companies as $co) {
            $c = $this->createCompany($co);
            $this->assignCompany($email, $c['company']['id'] ?? $co, $role);
        }
        return ['summary' => $out, 'companies' => $companies];
    }

    private function findCompany(string $idOrName): Company
    {
        $idOrName = trim($idOrName);

        if (Str::isUuid($idOrName)) {
            return Company::findOrFail($idOrName);
        }

        return Company::where('name', $idOrName)
            ->orWhere('slug', $idOrName)
            ->firstOrFail();
    }
}
