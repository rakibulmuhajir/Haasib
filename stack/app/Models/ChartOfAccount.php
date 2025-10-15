<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class ChartOfAccount extends Model
{
    use HasFactory;

    /**
     * The table associated with the model.
     */
    protected $table = 'accounting.chart_of_accounts';

    /**
     * The primary key associated with the table.
     */
    protected $primaryKey = 'account_code';

    /**
     * Indicates if the model's ID is auto-incrementing.
     */
    public $incrementing = false;

    /**
     * The data type of the primary key.
     */
    protected $keyType = 'string';

    /**
     * The attributes that are mass assignable.
     */
    protected $fillable = [
        'account_code',
        'account_name',
        'account_type',
        'account_category',
        'is_active',
        'description',
        'parent_account_code',
        'company_id',
        'created_at',
        'updated_at',
    ];

    /**
     * The attributes that should be cast.
     */
    protected $casts = [
        'is_active' => 'boolean',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    /**
     * Get the company that owns the chart of account.
     */
    public function company()
    {
        return $this->belongsTo(Company::class, 'company_id');
    }

    /**
     * Get the parent account.
     */
    public function parentAccount()
    {
        return $this->belongsTo(ChartOfAccount::class, 'parent_account_code', 'account_code');
    }

    /**
     * Get the child accounts.
     */
    public function childAccounts()
    {
        return $this->hasMany(ChartOfAccount::class, 'parent_account_code', 'account_code');
    }

    /**
     * Get the journal entries for this account.
     */
    public function journalEntries()
    {
        return $this->hasMany(JournalEntry::class, 'account_code', 'account_code');
    }

    /**
     * Scope to get active accounts.
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /**
     * Scope to get accounts by type.
     */
    public function scopeByType($query, string $accountType)
    {
        return $query->where('account_type', $accountType);
    }

    /**
     * Scope to get accounts by category.
     */
    public function scopeByCategory($query, string $accountCategory)
    {
        return $query->where('account_category', $accountCategory);
    }

    /**
     * Get the account type label.
     */
    public function getAccountTypeLabelAttribute(): string
    {
        $labels = [
            'asset' => 'Asset',
            'liability' => 'Liability',
            'equity' => 'Equity',
            'revenue' => 'Revenue',
            'expense' => 'Expense',
        ];

        return $labels[$this->account_type] ?? $this->account_type;
    }

    /**
     * Get the account category label.
     */
    public function getAccountCategoryLabelAttribute(): string
    {
        $labels = [
            'current_assets' => 'Current Assets',
            'fixed_assets' => 'Fixed Assets',
            'current_liabilities' => 'Current Liabilities',
            'long_term_liabilities' => 'Long-term Liabilities',
            'owner_equity' => 'Owner Equity',
            'operating_revenue' => 'Operating Revenue',
            'non_operating_revenue' => 'Non-operating Revenue',
            'operating_expenses' => 'Operating Expenses',
            'non_operating_expenses' => 'Non-operating Expenses',
        ];

        return $labels[$this->account_category] ?? $this->account_category;
    }
}