<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AuditEntry extends Model
{
    use HasFactory, HasUuids;

    public $incrementing = false;

    protected $keyType = 'string';

    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'auth.audit_entries';

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'company_id',
        'user_id',
        'event',
        'model_type',
        'model_id',
        'old_values',
        'new_values',
        'ip_address',
        'user_agent',
        'tags',
        'metadata',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'old_values' => 'array',
            'new_values' => 'array',
            'tags' => 'array',
            'metadata' => 'array',
            'company_id' => 'string',
            'user_id' => 'string',
            'model_id' => 'string',
        ];
    }

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'ip_address',
        'user_agent',
    ];

    /**
     * Get the company that owns the audit entry.
     */
    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    /**
     * Get the user that performed the action.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get the audited model instance.
     */
    public function getModel()
    {
        if (! $this->model_type || ! $this->model_id) {
            return null;
        }

        return $this->model_type::find($this->model_id);
    }

    /**
     * Scope a query to only include entries for a specific event.
     */
    public function scopeForEvent($query, string $event)
    {
        return $query->where('event', $event);
    }

    /**
     * Scope a query to only include entries for a specific model.
     */
    public function scopeForModel($query, string $modelType)
    {
        return $query->where('model_type', $modelType);
    }

    /**
     * Scope a query to only include entries with a specific tag.
     */
    public function scopeWithTag($query, string $tag)
    {
        return $query->whereJsonContains('tags', $tag);
    }

    /**
     * Scope a query to only include entries within a date range.
     */
    public function scopeBetweenDates($query, $startDate, $endDate)
    {
        return $query->whereBetween('created_at', [$startDate, $endDate]);
    }

    /**
     * Check if the audit entry is for a creation event.
     */
    public function isCreation(): bool
    {
        return $this->event === 'created';
    }

    /**
     * Check if the audit entry is for an update event.
     */
    public function isUpdate(): bool
    {
        return $this->event === 'updated';
    }

    /**
     * Check if the audit entry is for a deletion event.
     */
    public function isDeletion(): bool
    {
        return $this->event === 'deleted';
    }

    /**
     * Check if the audit entry has specific changes.
     */
    public function hasAttributeChange(string $attribute): bool
    {
        return array_key_exists($attribute, $this->old_values ?? [])
            || array_key_exists($attribute, $this->new_values ?? []);
    }

    /**
     * Get the old value of a specific attribute.
     */
    public function getOldValue(string $attribute, mixed $default = null): mixed
    {
        return data_get($this->old_values, $attribute, $default);
    }

    /**
     * Get the new value of a specific attribute.
     */
    public function getNewValue(string $attribute, mixed $default = null): mixed
    {
        return data_get($this->new_values, $attribute, $default);
    }

    /**
     * Get the diff between old and new values.
     */
    public function getDiff(): array
    {
        $diff = [];

        $allKeys = array_unique(array_merge(
            array_keys($this->old_values ?? []),
            array_keys($this->new_values ?? [])
        ));

        foreach ($allKeys as $key) {
            $old = $this->getOldValue($key);
            $new = $this->getNewValue($key);

            if ($old !== $new) {
                $diff[$key] = [
                    'old' => $old,
                    'new' => $new,
                ];
            }
        }

        return $diff;
    }

    /**
     * Create a new factory instance for the model.
     */
    protected static function newFactory(): Factory
    {
        return \Database\Factories\AuditEntryFactory::new();
    }
}
