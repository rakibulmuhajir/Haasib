<?php

namespace Modules\Accounting\Services;

use App\Models\AuditEntry;
use App\Models\Company;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class CompanyService
{
    public function __construct(private readonly ModuleService $moduleService) {}

    /**
     * Create a company and attach an owner.
     */
    public function createCompany(array $data, User $owner, array $modules = []): Company
    {
        $validator = Validator::make($data, [
            'name' => ['required', 'string', 'max:255'],
            'slug' => ['nullable', 'string', 'max:255', 'unique:companies,slug'],
            'country' => ['nullable', 'string', 'max:100'],
            'base_currency' => ['nullable', 'string', 'max:3'],
        ]);

        if ($validator->fails()) {
            throw new ValidationException($validator);
        }

        return DB::transaction(function () use ($validator, $owner, $modules) {
            $payload = $validator->validated();
            $payload['slug'] = $payload['slug'] ?? Str::slug($payload['name']);
            $payload['base_currency'] = $payload['base_currency'] ?? 'USD';
            $payload['created_by_user_id'] = $owner->id;
            $payload['is_active'] = true;

            $company = Company::create($payload);

            $company->users()->syncWithoutDetaching([
                $owner->id => [
                    'role' => 'owner',
                    'invited_by_user_id' => $owner->id,
                    'is_active' => true,
                    'joined_at' => now(),
                    'created_at' => now(),
                    'updated_at' => now(),
                ],
            ]);

            foreach ($modules as $moduleKey) {
                $this->moduleService->enableModule($company, $moduleKey, $owner);
            }

            $this->logAudit('company_created', $owner, $company, $payload);

            return $company;
        });
    }

    public function updateCompany(Company $company, array $data, User $performedBy): Company
    {
        $company->fill($data);
        $company->save();

        $this->logAudit('company_updated', $performedBy, $company, $data);

        return $company->fresh();
    }

    public function deactivateCompany(Company $company, User $performedBy): void
    {
        $company->deactivate();

        $this->logAudit('company_deactivated', $performedBy, $company);
    }

    public function reactivateCompany(Company $company, User $performedBy): void
    {
        $company->activate();

        $this->logAudit('company_reactivated', $performedBy, $company);
    }

    public function listCompanies(?User $user = null, int $perPage = 25): LengthAwarePaginator
    {
        $query = Company::query()->orderBy('name');

        if ($user && ! $user->isSuperAdmin()) {
            $query->whereHas('users', function ($q) use ($user) {
                $q->where('auth.company_user.user_id', $user->id)
                    ->where('auth.company_user.is_active', true);
            });
        }

        return $query->paginate($perPage);
    }

    public function addUser(Company $company, User $user, string $role, User $performedBy): void
    {
        $company->users()->syncWithoutDetaching([
            $user->id => [
                'role' => $role,
                'invited_by_user_id' => $performedBy->id,
                'is_active' => true,
                'joined_at' => now(),
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);

        $this->logAudit('company_user_added', $performedBy, $company, [
            'target_user_id' => $user->id,
            'role' => $role,
        ]);
    }

    public function removeUser(Company $company, User $user, User $performedBy): void
    {
        $company->users()->updateExistingPivot($user->id, [
            'is_active' => false,
            'left_at' => now(),
            'updated_at' => now(),
        ]);

        $this->logAudit('company_user_removed', $performedBy, $company, [
            'target_user_id' => $user->id,
        ]);
    }

    public function enableModule(Company $company, string $moduleKey, User $performedBy, array $settings = []): void
    {
        $this->moduleService->enableModule($company, $moduleKey, $performedBy, $settings);
    }

    public function disableModule(Company $company, string $moduleKey, User $performedBy): void
    {
        $this->moduleService->disableModule($company, $moduleKey, $performedBy);
    }

    public function getCompanyWithRelations(string $companyId, ?User $user = null): ?Company
    {
        $query = Company::with(['users', 'modules', 'auditEntries' => fn ($q) => $q->latest()->limit(50)]);

        if ($user && ! $user->isSuperAdmin()) {
            $query->whereHas('users', fn ($q) => $q->where('auth.company_user.user_id', $user->id));
        }

        return $query->find($companyId);
    }

    protected function logAudit(string $event, User $actor, Company $company, array $payload = []): void
    {
        AuditEntry::create([
            'event' => $event,
            'model_type' => Company::class,
            'model_id' => $company->id,
            'company_id' => $company->id,
            'user_id' => $actor->id,
            'new_values' => $payload,
            'metadata' => $payload,
        ]);
    }
}
