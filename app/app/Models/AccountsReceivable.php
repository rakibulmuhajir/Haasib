<?php

namespace App\Models;

use Brick\Money\Money;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

class AccountsReceivable extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'acct.accounts_receivable';

    protected $primaryKey = 'ar_id';

    protected $keyType = 'string';

    public $incrementing = false;

    protected $fillable = [
        'ar_id',
        'company_id',
        'customer_id',
        'invoice_id',
        'amount_due',
        'original_amount',
        'currency_id',
        'due_date',
        'days_overdue',
        'aging_category',
        'last_calculated_at',
        'metadata',
    ];

    protected $casts = [
        'amount_due' => 'decimal:2',
        'original_amount' => 'decimal:2',
        'due_date' => 'date',
        'days_overdue' => 'integer',
        'last_calculated_at' => 'datetime',
        'metadata' => 'array',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
        'deleted_at' => 'datetime',
    ];

    protected $attributes = [
        'days_overdue' => 0,
        'aging_category' => 'current',
    ];

    protected static function boot(): void
    {
        parent::boot();

        static::creating(function ($model) {
            $model->ar_id = $model->ar_id ?: (string) Str::uuid();
        });

        static::created(function ($ar) {
            $ar->calculateAging();
        });

        static::saving(function ($ar) {
            if ($ar->isDirty(['amount_due', 'due_date'])) {
                $ar->calculateAging();
            }
        });
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class, 'customer_id', 'customer_id');
    }

    public function invoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class);
    }

    public function currency(): BelongsTo
    {
        return $this->belongsTo(Currency::class);
    }

    public function scopeForCompany($query, $companyId)
    {
        return $query->where('company_id', $companyId);
    }

    public function scopeForCustomer($query, $customerId)
    {
        return $query->where('customer_id', $customerId);
    }

    public function scopeCurrent($query)
    {
        return $query->where('aging_category', 'current');
    }

    public function scopeOverdue($query)
    {
        return $query->where('days_overdue', '>', 0);
    }

    public function scopeByAgingCategory($query, $category)
    {
        return $query->where('aging_category', $category);
    }

    public function scopeBetweenAges($query, $minDays, $maxDays)
    {
        return $query->whereBetween('days_overdue', [$minDays, $maxDays]);
    }

    public function scopeDueBetween($query, $startDate, $endDate)
    {
        return $query->whereBetween('due_date', [$startDate, $endDate]);
    }

    public function getAmountDue(): Money
    {
        return Money::of($this->amount_due, $this->currency->code);
    }

    public function getOriginalAmount(): Money
    {
        return Money::of($this->original_amount, $this->currency->code);
    }

    public function getAmountPaid(): Money
    {
        return $this->getOriginalAmount()->minus($this->getAmountDue());
    }

    public function getPaymentPercentage(): float
    {
        if ($this->original_amount <= 0) {
            return 0;
        }

        return ($this->getAmountPaid()->getAmount()->toFloat() / $this->original_amount) * 100;
    }

    public function isCurrent(): bool
    {
        return $this->aging_category === 'current';
    }

    public function isOverdue(): bool
    {
        return $this->days_overdue > 0;
    }

    public function isPastDue(): bool
    {
        return $this->isOverdue();
    }

    public function isWithinGracePeriod(): bool
    {
        return $this->days_overdue <= $this->getGracePeriodDays();
    }

    public function isInCollections(): bool
    {
        return $this->days_overdue > 90;
    }

    public function isWriteOffCandidate(): bool
    {
        return $this->days_overdue > 180 && $this->amount_due > 0;
    }

    public function getGracePeriodDays(): int
    {
        return $this->company->settings['ar_grace_period_days'] ?? 10;
    }

    public function getCollectionsThresholdDays(): int
    {
        return $this->company->settings['ar_collections_threshold_days'] ?? 90;
    }

    public function getWriteOffThresholdDays(): int
    {
        return $this->company->settings['ar_write_off_threshold_days'] ?? 180;
    }

    public function calculateAging(): void
    {
        $this->days_overdue = $this->calculateDaysOverdue();
        $this->aging_category = $this->determineAgingCategory();
        $this->last_calculated_at = now();
    }

    public function calculateDaysOverdue(): int
    {
        if (! $this->due_date) {
            return 0;
        }

        if ($this->amount_due <= 0) {
            return 0;
        }

        $dueDate = \Carbon\Carbon::parse($this->due_date);
        $today = \Carbon\Carbon::today();

        if ($today->lte($dueDate)) {
            return 0;
        }

        return $today->diffInDays($dueDate);
    }

    public function determineAgingCategory(): string
    {
        if ($this->days_overdue <= 0) {
            return 'current';
        }

        if ($this->days_overdue <= 30) {
            return '1-30';
        }

        if ($this->days_overdue <= 60) {
            return '31-60';
        }

        if ($this->days_overdue <= 90) {
            return '61-90';
        }

        return '90+';
    }

    public function getAgingCategoryLabel(): string
    {
        return match ($this->aging_category) {
            'current' => 'Current',
            '1-30' => '1-30 Days',
            '31-60' => '31-60 Days',
            '61-90' => '61-90 Days',
            '90+' => '90+ Days',
            default => ucfirst($this->aging_category),
        };
    }

    public function getAgingCategoryColor(): string
    {
        return match ($this->aging_category) {
            'current' => 'green',
            '1-30' => 'yellow',
            '31-60' => 'orange',
            '61-90' => 'red',
            '90+' => 'dark-red',
            default => 'gray',
        };
    }

    public function updateFromInvoice(): void
    {
        if (! $this->invoice) {
            return;
        }

        $invoice = $this->invoice;
        $this->amount_due = $invoice->balance_due;
        $this->original_amount = $invoice->total_amount;
        $this->due_date = $invoice->due_date;
        $this->calculateAging();
        $this->save();
    }

    public static function updateForCustomer(string $customerId): void
    {
        \App\Jobs\AccountsReceivable\UpdateForCustomer::dispatch($customerId);
    }

    public static function updateForCompany(int $companyId): void
    {
        \App\Jobs\AccountsReceivable\UpdateForCompany::dispatch($companyId);
    }

    public static function getAgingReport(int $companyId): array
    {
        $initialReport = [
            'current' => ['count' => 0, 'amount' => 0],
            '1-30' => ['count' => 0, 'amount' => 0],
            '31-60' => ['count' => 0, 'amount' => 0],
            '61-90' => ['count' => 0, 'amount' => 0],
            '90+' => ['count' => 0, 'amount' => 0],
        ];

        $results = static::where('company_id', $companyId)
            ->where('amount_due', '>', 0)
            ->selectRaw('aging_category, count(*) as count, sum(amount_due) as amount')
            ->groupBy('aging_category')
            ->get()
            ->keyBy('aging_category');

        // Merge DB results with the initial structure to ensure all keys exist
        $report = array_merge($initialReport, $results->toArray());

        // Calculate total
        $report['total'] = [
            'count' => array_sum(array_column($report, 'count')),
            'amount' => array_sum(array_column($report, 'amount')),
        ];

        return $report;
    }

    public static function getCustomerAgingSummary(int $customerId, int $companyId): array
    {
        $records = static::where('company_id', $companyId)
            ->where('customer_id', $customerId)
            ->where('amount_due', '>', 0)
            ->get();

        $summary = [
            'total_outstanding' => 0,
            'total_original' => 0,
            'current' => 0,
            '1-30' => 0,
            '31-60' => 0,
            '61-90' => 0,
            '90+' => 0,
            'overdue_invoices' => 0,
            'oldest_overdue_days' => 0,
        ];

        foreach ($records as $record) {
            $summary['total_outstanding'] += $record->amount_due;
            $summary['total_original'] += $record->original_amount;

            if (isset($summary[$record->aging_category])) {
                $summary[$record->aging_category] += $record->amount_due;
            }

            if ($record->days_overdue > 0) {
                $summary['overdue_invoices']++;
                $summary['oldest_overdue_days'] = max($summary['oldest_overdue_days'], $record->days_overdue);
            }
        }

        return $summary;
    }

    public function getDisplayAmountDue(): string
    {
        return number_format($this->amount_due, 2).' '.$this->currency->code;
    }

    public function getDisplayOriginalAmount(): string
    {
        return number_format($this->original_amount, 2).' '.$this->currency->code;
    }

    public function getDisplayDaysOverdue(): string
    {
        return $this->days_overdue > 0 ? $this->days_overdue.' days' : 'Current';
    }

    public function getDisplayAgingCategory(): string
    {
        return $this->getAgingCategoryLabel();
    }

    public function getRiskLevel(): string
    {
        if ($this->days_overdue <= 0) {
            return 'low';
        }

        if ($this->days_overdue <= 30) {
            return 'medium';
        }

        if ($this->days_overdue <= 90) {
            return 'high';
        }

        return 'critical';
    }

    public function getRiskLevelColor(): string
    {
        return match ($this->getRiskLevel()) {
            'low' => 'green',
            'medium' => 'yellow',
            'high' => 'orange',
            'critical' => 'red',
            default => 'gray',
        };
    }

    public function getCollectionAction(): ?string
    {
        if ($this->days_overdue <= 0) {
            return null;
        }

        if ($this->days_overdue <= 30) {
            return 'reminder';
        }

        if ($this->days_overdue <= 60) {
            return 'follow_up';
        }

        if ($this->days_overdue <= 90) {
            return 'final_notice';
        }

        return 'collections';
    }
}
