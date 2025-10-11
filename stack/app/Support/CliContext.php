<?php

namespace App\Support;

use App\Models\Company;
use App\Models\User;
use Illuminate\Support\Facades\File;

class CliContext
{
    /**
     * Path to the context payload on disk.
     */
    protected string $path;

    public function __construct(?string $path = null)
    {
        $this->path = $path ?? storage_path('cli-context.json');
    }

    /**
     * Persist the acting user identifier for CLI operations.
     */
    public function rememberUser(User $user): void
    {
        $data = $this->read();
        $data['user_id'] = $user->id;

        if (! empty($data['company_id']) && ! $user->companies()->where('auth.company_user.company_id', $data['company_id'])->exists()) {
            unset($data['company_id']);
        }

        $this->write($data);
    }

    public function forgetUser(): void
    {
        $data = $this->read();
        unset($data['user_id'], $data['company_id']);
        $this->write($data);
    }

    public function rememberCompany(Company $company): void
    {
        $data = $this->read();
        $data['company_id'] = $company->id;
        $this->write($data);
    }

    public function forgetCompany(): void
    {
        $data = $this->read();
        unset($data['company_id']);
        $this->write($data);
    }

    public function getUser(): ?User
    {
        $userId = $this->read()['user_id'] ?? null;

        return $userId ? User::find($userId) : null;
    }

    public function getCompany(): ?Company
    {
        $companyId = $this->read()['company_id'] ?? null;

        return $companyId ? Company::find($companyId) : null;
    }

    protected function read(): array
    {
        if (! File::exists($this->path)) {
            return [];
        }

        $json = File::get($this->path);

        return json_decode($json, true) ?: [];
    }

    protected function write(array $data): void
    {
        File::ensureDirectoryExists(dirname($this->path));
        File::put($this->path, json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
    }
}
