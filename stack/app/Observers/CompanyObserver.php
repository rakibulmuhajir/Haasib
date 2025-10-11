<?php

namespace App\Observers;

use App\Models\AuditEntry;
use App\Models\Company;
use App\Models\CompanyInvitation;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class CompanyObserver
{
    public function created(Company $company): void
    {
        $this->createAuditEntry($company, 'create', [
            'old_values' => null,
            'new_values' => $this->getCompanyValues($company),
        ]);
    }

    public function updated(Company $company): void
    {
        $changes = $company->getChanges();
        $original = $company->getOriginal();
        
        $this->createAuditEntry($company, 'update', [
            'old_values' => $this->filterChangedValues($original, $changes),
            'new_values' => $this->filterChangedValues($changes, $changes),
        ]);
    }

    public function deleted(Company $company): void
    {
        $this->createAuditEntry($company, 'delete', [
            'old_values' => $this->getCompanyValues($company),
            'new_values' => null,
        ]);
    }

    public function restored(Company $company): void
    {
        $this->createAuditEntry($company, 'restore', [
            'old_values' => null,
            'new_values' => $this->getCompanyValues($company),
        ]);
    }

    private function createAuditEntry(Company $company, string $action, array $data): void
    {
        try {
            $user = Auth::user();
            
            // Generate idempotency key for this audit entry
            $idempotencyKey = DB::selectOne("SELECT gen_random_uuid() as uuid")->uuid;
            
            AuditEntry::create([
                'company_id' => $company->id,
                'entity_type' => 'Company',
                'entity_id' => $company->id,
                'action' => $action,
                'old_values' => json_encode($data['old_values']),
                'new_values' => json_encode($data['new_values']),
                'idempotency_key' => $idempotencyKey,
                'user_id' => $user?->id,
                'ip_address' => request()?->ip(),
                'user_agent' => request()?->userAgent(),
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        } catch (\Exception $e) {
            // Fail silently to avoid breaking the application
            logger()->error('Company audit entry creation failed', [
                'error' => $e->getMessage(),
                'company_id' => $company->id,
                'action' => $action,
            ]);
        }
    }

    private function getCompanyValues(Company $company): array
    {
        return [
            'name' => $company->name,
            'industry' => $company->industry,
            'slug' => $company->slug,
            'country' => $company->country,
            'base_currency' => $company->base_currency,
            'currency' => $company->currency,
            'timezone' => $company->timezone,
            'language' => $company->language,
            'locale' => $company->locale,
            'is_active' => $company->is_active,
        ];
    }

    private function filterChangedValues(array $values, array $changes): array
    {
        return array_intersect_key($values, $changes);
    }
}