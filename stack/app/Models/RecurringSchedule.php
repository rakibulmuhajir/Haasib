<?php

namespace App\Models;

use App\Models\Concerns\BelongsToCompany;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class RecurringSchedule extends Model
{
    use BelongsToCompany, HasFactory, HasUuids, SoftDeletes;

    protected $connection = 'pgsql';

    protected $table = 'acct.recurring_schedules';

    protected $primaryKey = 'id';

    protected $keyType = 'string';

    public $incrementing = false;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'company_id',
        'customer_id',
        'name',
        'frequency',
        'interval',
        'start_date',
        'end_date',
        'next_invoice_date',
        'last_generated_at',
        'template_data',
        'is_active',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'company_id' => 'string',
            'customer_id' => 'string',
            'interval' => 'integer',
            'start_date' => 'date',
            'end_date' => 'date',
            'next_invoice_date' => 'date',
            'last_generated_at' => 'datetime',
            'template_data' => 'array',
            'is_active' => 'boolean',
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
            'deleted_at' => 'datetime',
        ];
    }

    /**
     * Get the company that owns the recurring schedule.
     */
    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    /**
     * Get the customer this recurring schedule belongs to.
     */
    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class, 'customer_id');
    }

    /**
     * Get the invoices generated from this recurring schedule.
     */
    public function invoices(): HasMany
    {
        return $this->hasMany(Invoice::class, 'recurring_schedule_id');
    }

    /**
     * Scope schedules to only include active ones.
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /**
     * Scope schedules for a specific company.
     */
    public function scopeForCompany($query, string $companyId)
    {
        return $query->where('company_id', $companyId);
    }

    /**
     * Scope schedules for a specific customer.
     */
    public function scopeForCustomer($query, string $customerId)
    {
        return $query->where('customer_id', $customerId);
    }

    /**
     * Scope schedules that are due to generate.
     */
    public function scopeDue($query, string $date = null)
    {
        $date = $date ?? now()->toDateString();

        return $query->where('next_invoice_date', '<=', $date)
            ->where('is_active', true)
            ->where(function ($subQuery) use ($date) {
                $subQuery->whereNull('end_date')
                    ->orWhere('end_date', '>=', $date);
            });
    }

    /**
     * Check if the schedule is active and should generate invoices.
     */
    public function shouldGenerateInvoice(): bool
    {
        if (!$this->is_active) {
            return false;
        }

        if ($this->next_invoice_date > now()->toDateString()) {
            return false;
        }

        if ($this->end_date && $this->end_date < now()->toDateString()) {
            return false;
        }

        return true;
    }

    /**
     * Generate the next invoice date based on frequency.
     */
    public function calculateNextInvoiceDate(): string
    {
        $currentDate = \Carbon\Carbon::parse($this->next_invoice_date);

        return match ($this->frequency) {
            'daily' => $currentDate->addDays($this->interval)->toDateString(),
            'weekly' => $currentDate->addWeeks($this->interval)->toDateString(),
            'monthly' => $currentDate->addMonths($this->interval)->toDateString(),
            'quarterly' => $currentDate->addQuarters($this->interval)->toDateString(),
            'yearly' => $currentDate->addYears($this->interval)->toDateString(),
            default => $currentDate->addMonths($this->interval)->toDateString(),
        };
    }

    /**
     * Boot the model.
     */
    protected static function booted()
    {
        static::creating(function ($schedule) {
            if (!$schedule->next_invoice_date) {
                $schedule->next_invoice_date = $schedule->start_date;
            }

            if ($schedule->is_active === null) {
                $schedule->is_active = true;
            }
        });
    }
}