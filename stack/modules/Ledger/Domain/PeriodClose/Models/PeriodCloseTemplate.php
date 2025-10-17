<?php

namespace Modules\Ledger\Domain\PeriodClose\Models;

use App\Models\Company;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PeriodCloseTemplate extends Model
{
    use HasFactory, HasUuids;

    public $incrementing = false;

    protected $keyType = 'string';

    protected $table = 'ledger.period_close_templates';

    protected $fillable = [
        'company_id',
        'name',
        'frequency',
        'is_default',
        'active',
        'description',
        'metadata',
    ];

    protected function casts(): array
    {
        return [
            'is_default' => 'boolean',
            'active' => 'boolean',
            'metadata' => 'array',
            'company_id' => 'string',
        ];
    }

    /**
     * Get the company that owns the template.
     */
    public function company(): HasMany
    {
        return $this->hasMany(Company::class);
    }

    /**
     * Get the tasks for this template.
     */
    public function tasks(): HasMany
    {
        return $this->hasMany(PeriodCloseTemplateTask::class)->orderBy('sequence');
    }

    /**
     * Get the required tasks for this template.
     */
    public function requiredTasks(): HasMany
    {
        return $this->hasMany(PeriodCloseTemplateTask::class)
            ->where('is_required', true)
            ->orderBy('sequence');
    }

    /**
     * Get the optional tasks for this template.
     */
    public function optionalTasks(): HasMany
    {
        return $this->hasMany(PeriodCloseTemplateTask::class)
            ->where('is_required', false)
            ->orderBy('sequence');
    }

    /**
     * Check if the template is active.
     */
    public function isActive(): bool
    {
        return $this->active;
    }

    /**
     * Check if the template is the default.
     */
    public function isDefault(): bool
    {
        return $this->is_default;
    }

    /**
     * Activate the template.
     */
    public function activate(): void
    {
        $this->active = true;
        $this->save();
    }

    /**
     * Deactivate the template.
     */
    public function deactivate(): void
    {
        $this->active = false;
        $this->save();
    }

    /**
     * Set as default template.
     */
    public function setAsDefault(): void
    {
        // Remove default flag from other templates for the same company
        static::where('company_id', $this->company_id)
            ->where('id', '!=', $this->id)
            ->update(['is_default' => false]);

        $this->is_default = true;
        $this->save();
    }

    /**
     * Get the total number of tasks.
     */
    public function getTotalTasksCount(): int
    {
        return $this->tasks()->count();
    }

    /**
     * Get the number of required tasks.
     */
    public function getRequiredTasksCount(): int
    {
        return $this->requiredTasks()->count();
    }

    /**
     * Get the number of optional tasks.
     */
    public function getOptionalTasksCount(): int
    {
        return $this->optionalTasks()->count();
    }

    /**
     * Check if the template has any tasks.
     */
    public function hasTasks(): bool
    {
        return $this->tasks()->exists();
    }

    /**
     * Check if the template is valid (has at least one task).
     */
    public function isValid(): bool
    {
        return $this->hasTasks();
    }

    /**
     * Get tasks grouped by category.
     */
    public function getTasksByCategory(): array
    {
        return $this->tasks()
            ->get()
            ->groupBy('category')
            ->toArray();
    }

    /**
     * Get the frequency display name.
     */
    public function getFrequencyDisplay(): string
    {
        return match ($this->frequency) {
            'monthly' => 'Monthly',
            'quarterly' => 'Quarterly',
            'annual' => 'Annual',
            default => ucfirst($this->frequency),
        };
    }

    /**
     * Clone the template with a new name.
     */
    public function clone(string $newName): self
    {
        $clone = $this->replicate();
        $clone->name = $newName;
        $clone->is_default = false; // Cloned templates are never default
        $clone->save();

        // Clone all tasks
        foreach ($this->tasks as $task) {
            $taskClone = $task->replicate();
            $taskClone->template_id = $clone->id;
            $taskClone->save();
        }

        return $clone;
    }

    /**
     * Archive the template (deactivate and remove default flag).
     */
    public function archive(): void
    {
        $this->active = false;
        if ($this->is_default) {
            $this->is_default = false;
        }
        $this->save();
    }

    /**
     * Restore the template (activate).
     */
    public function restore(): void
    {
        $this->activate();
    }

    /**
     * Add a task to the template.
     */
    public function addTask(array $taskData): PeriodCloseTemplateTask
    {
        // If sequence is not provided, put it at the end
        if (! isset($taskData['sequence'])) {
            $maxSequence = $this->tasks()->max('sequence') ?? 0;
            $taskData['sequence'] = $maxSequence + 1;
        }

        return $this->tasks()->create($taskData);
    }

    /**
     * Update task sequence ordering.
     */
    public function reorderTasks(array $taskIds): bool
    {
        $tasks = $this->tasks()->whereIn('id', $taskIds)->get();

        if ($tasks->count() !== count($taskIds)) {
            return false; // Some tasks not found
        }

        foreach ($taskIds as $index => $taskId) {
            $task = $tasks->firstWhere('id', $taskId);
            if ($task) {
                $task->sequence = $index + 1;
                $task->save();
            }
        }

        return true;
    }

    /**
     * Get the validation rules for this template.
     */
    public function getValidationRules(): array
    {
        return [
            'name' => ['required', 'string', 'max:120'],
            'frequency' => ['required', 'in:monthly,quarterly,annual'],
            'description' => ['nullable', 'string'],
            'is_default' => ['boolean'],
            'active' => ['boolean'],
        ];
    }

    /**
     * Scope a query to only include active templates.
     */
    public function scopeActive($query)
    {
        return $query->where('active', true);
    }

    /**
     * Scope a query to only include default templates.
     */
    public function scopeDefault($query)
    {
        return $query->where('is_default', true);
    }

    /**
     * Scope a query to only include templates for a specific frequency.
     */
    public function scopeWithFrequency($query, $frequency)
    {
        return $query->where('frequency', $frequency);
    }

    /**
     * Scope a query to only include templates for a specific company.
     */
    public function scopeForCompany($query, $companyId)
    {
        return $query->where('company_id', $companyId);
    }

    /**
     * Get the default template for a company.
     */
    public static function getDefaultForCompany(string $companyId): ?self
    {
        return static::where('company_id', $companyId)
            ->where('is_default', true)
            ->where('active', true)
            ->first();
    }

    /**
     * Get active templates for a company.
     */
    public static function getActiveForCompany(string $companyId): \Illuminate\Database\Eloquent\Collection
    {
        return static::where('company_id', $companyId)
            ->where('active', true)
            ->orderBy('is_default', 'desc')
            ->orderBy('name')
            ->get();
    }
}
