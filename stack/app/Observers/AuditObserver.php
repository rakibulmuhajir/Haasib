<?php

namespace App\Observers;

use App\Models\AuditEntry;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;

class AuditObserver
{
    /**
     * Handle the model "created" event.
     */
    public function created(Model $model): void
    {
        $this->logAuditEvent('created', $model, null, $model->getAttributes());
    }

    /**
     * Handle the model "updated" event.
     */
    public function updated(Model $model): void
    {
        $changes = $model->getChanges();
        $original = $model->getOriginal();

        // Filter out system fields that don't need auditing
        $auditableChanges = $this->filterAuditableFields($changes, $original);

        if (! empty($auditableChanges)) {
            $oldValues = [];
            $newValues = [];

            foreach ($auditableChanges as $key => $newValue) {
                $oldValues[$key] = $original[$key] ?? null;
                $newValues[$key] = $newValue;
            }

            $this->logAuditEvent('updated', $model, $oldValues, $newValues);
        }
    }

    /**
     * Handle the model "deleted" event.
     */
    public function deleted(Model $model): void
    {
        $this->logAuditEvent('deleted', $model, $model->getOriginal(), null);
    }

    /**
     * Handle the model "restored" event.
     */
    public function restored(Model $model): void
    {
        $this->logAuditEvent('restored', $model, null, $model->getAttributes());
    }

    /**
     * Log an audit event.
     */
    protected function logAuditEvent(string $event, Model $model, ?array $oldValues = null, ?array $newValues = null): void
    {
        try {
            $user = $this->getCurrentUser();
            $company = $this->getCurrentCompany($model);

            $tags = $this->generateTags($event, $model);
            $metadata = $this->generateMetadata($event, $model, $oldValues, $newValues);

            AuditEntry::create([
                'company_id' => $company?->id,
                'user_id' => $user?->id,
                'event' => $event,
                'model_type' => get_class($model),
                'model_id' => $model->getKey(),
                'old_values' => $oldValues,
                'new_values' => $newValues,
                'ip_address' => request()->ip(),
                'user_agent' => request()->userAgent(),
                'tags' => $tags,
                'metadata' => $metadata,
            ]);
        } catch (\Exception $e) {
            // Log audit failures but don't break the application
            \Log::warning('Failed to log audit entry', [
                'event' => $event,
                'model' => get_class($model),
                'model_id' => $model->getKey(),
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Get the current user.
     */
    protected function getCurrentUser(): ?User
    {
        return auth()->user();
    }

    /**
     * Get the current company from the model or request.
     */
    protected function getCurrentCompany(Model $model): ?\App\Models\Company
    {
        // Try to get company from the model itself
        if (method_exists($model, 'company') && $model->company) {
            return $model->company;
        }

        // Try to get company_id from the model
        if (isset($model->company_id)) {
            return \App\Models\Company::find($model->company_id);
        }

        // Fall back to current user's company
        if (auth()->check() && auth()->user()->company) {
            return auth()->user()->company;
        }

        return null;
    }

    /**
     * Filter out fields that don't need auditing.
     */
    protected function filterAuditableFields(array $changes, array $original): array
    {
        $nonAuditableFields = [
            'updated_at',
            'created_at',
            'deleted_at',
            'remember_token',
            'email_verified_at',
            'password_changed_at',
        ];

        $auditableChanges = [];

        foreach ($changes as $key => $newValue) {
            if (in_array($key, $nonAuditableFields)) {
                continue;
            }

            $oldValue = $original[$key] ?? null;

            // Skip if the value didn't actually change
            if ($oldValue === $newValue) {
                continue;
            }

            // Skip password changes (log separately for security)
            if ($key === 'password') {
                $this->logPasswordChange($oldValue, $newValue);

                continue;
            }

            $auditableChanges[$key] = $newValue;
        }

        return $auditableChanges;
    }

    /**
     * Generate tags for the audit entry.
     */
    protected function generateTags(string $event, Model $model): array
    {
        $tags = [$event];

        $modelName = class_basename($model);
        $tags[] = strtolower($modelName);

        // Add model-specific tags
        if (method_exists($model, 'getAuditTags')) {
            $modelTags = $model->getAuditTags();
            $tags = array_merge($tags, $modelTags);
        }

        // Add financial tags for money-related models
        $financialModels = ['Invoice', 'Payment', 'Bill', 'Expense', 'JournalEntry'];
        if (in_array($modelName, $financialModels)) {
            $tags[] = 'financial';
            $tags[] = strtolower($modelName);
        }

        // Add security tags for sensitive models
        $securityModels = ['User', 'Company', 'Role', 'Permission'];
        if (in_array($modelName, $securityModels)) {
            $tags[] = 'security';
            $tags[] = 'access_control';
        }

        return array_unique($tags);
    }

    /**
     * Generate metadata for the audit entry.
     */
    protected function generateMetadata(string $event, Model $model, ?array $oldValues = null, ?array $newValues = null): array
    {
        $metadata = [
            'model_name' => class_basename($model),
            'model_id' => $model->getKey(),
        ];

        // Add financial metadata for money-related changes
        if ($this->isFinancialChange($oldValues, $newValues)) {
            $metadata['financial_impact'] = true;
            $metadata['currency_changes'] = $this->getCurrencyChanges($oldValues, $newValues);
        }

        // Add user context
        if (auth()->check()) {
            $metadata['user_context'] = [
                'id' => auth()->id(),
                'name' => auth()->user()->name,
                'email' => auth()->user()->email,
            ];
        }

        // Add request context
        if (request()->hasHeader('X-Requested-With')) {
            $metadata['ajax_request'] = true;
        }

        return $metadata;
    }

    /**
     * Check if the change involves financial data.
     */
    protected function isFinancialChange(?array $oldValues, ?array $newValues): bool
    {
        $financialFields = ['amount', 'balance', 'total', 'price', 'cost', 'rate', 'value'];

        foreach (array_merge(array_keys($oldValues ?? []), array_keys($newValues ?? [])) as $field) {
            if (preg_match('/('.implode('|', $financialFields).')/i', $field)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Extract currency-related changes.
     */
    protected function getCurrencyChanges(?array $oldValues, ?array $newValues): array
    {
        $changes = [];

        foreach (array_merge($oldValues ?? [], $newValues ?? []) as $field => $value) {
            if (preg_match('/(amount|balance|total|price|cost|rate|value)/i', $field)) {
                $changes[$field] = [
                    'old' => $oldValues[$field] ?? null,
                    'new' => $newValues[$field] ?? null,
                ];
            }
        }

        return $changes;
    }

    /**
     * Log password changes separately for security monitoring.
     */
    protected function logPasswordChange(?string $oldValue, ?string $newValue): void
    {
        if (auth()->check()) {
            AuditEntry::create([
                'company_id' => auth()->user()->company_id,
                'user_id' => auth()->id(),
                'event' => 'password_changed',
                'model_type' => User::class,
                'model_id' => auth()->id(),
                'old_values' => ['password_changed' => true],
                'new_values' => ['password_changed_at' => now()],
                'ip_address' => request()->ip(),
                'user_agent' => request()->userAgent(),
                'tags' => ['security', 'password', 'sensitive'],
                'metadata' => [
                    'security_impact' => true,
                    'user_context' => [
                        'id' => auth()->id(),
                        'name' => auth()->user()->name,
                    ],
                ],
            ]);
        }
    }
}
