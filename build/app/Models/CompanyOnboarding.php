<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CompanyOnboarding extends Model
{
    use HasUuids;

    protected $connection = 'pgsql';
    protected $table = 'auth.company_onboarding';
    protected $keyType = 'string';
    public $incrementing = false;

    protected $fillable = [
        'company_id',
        'current_step',
        'step_number',
        'completed_steps',
        'step_data',
        'is_completed',
        'completed_at',
    ];

    protected $casts = [
        'company_id' => 'string',
        'step_number' => 'integer',
        'completed_steps' => 'array',
        'step_data' => 'array',
        'is_completed' => 'boolean',
        'completed_at' => 'datetime',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    /**
     * Mark a step as completed.
     */
    public function completeStep(string $stepName): void
    {
        $completedSteps = $this->completed_steps ?? [];

        if (!in_array($stepName, $completedSteps)) {
            $completedSteps[] = $stepName;
            $this->completed_steps = $completedSteps;
            $this->save();
        }
    }

    /**
     * Check if a step is completed.
     */
    public function isStepCompleted(string $stepName): bool
    {
        return in_array($stepName, $this->completed_steps ?? []);
    }

    /**
     * Store temporary data for a step.
     */
    public function setStepData(string $stepName, array $data): void
    {
        $stepData = $this->step_data ?? [];
        $stepData[$stepName] = $data;
        $this->step_data = $stepData;
        $this->save();
    }

    /**
     * Get temporary data for a step.
     */
    public function getStepData(string $stepName): ?array
    {
        return $this->step_data[$stepName] ?? null;
    }

    /**
     * Move to the next step.
     */
    public function advanceToStep(string $stepName, int $stepNumber): void
    {
        $this->current_step = $stepName;
        $this->step_number = $stepNumber;
        $this->save();
    }

    /**
     * Complete the entire onboarding process.
     */
    public function complete(): void
    {
        $this->is_completed = true;
        $this->completed_at = now();
        $this->save();

        // Update company record
        $this->company->update([
            'onboarding_completed' => true,
            'onboarding_completed_at' => now(),
        ]);
    }
}
