<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ExpenseCategory extends Model
{
    use HasFactory;

    protected $table = 'acct.expense_categories';

    protected $keyType = 'string';

    public $incrementing = false;

    protected $fillable = [
        'id',
        'company_id',
        'name',
        'code',
        'description',
        'type',
        'is_active',
        'parent_id',
        'color',
        'sort_order',
    ];

    protected $casts = [
        'id' => 'string',
        'company_id' => 'string',
        'parent_id' => 'string',
        'is_active' => 'boolean',
        'sort_order' => 'integer',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
        'deleted_at' => 'datetime',
    ];

    // Relationships
    public function company()
    {
        return $this->belongsTo(Company::class, 'company_id', 'id');
    }

    public function parent()
    {
        return $this->belongsTo(ExpenseCategory::class, 'parent_id', 'id');
    }

    public function children()
    {
        return $this->hasMany(ExpenseCategory::class, 'parent_id', 'id');
    }

    public function expenses()
    {
        return $this->hasMany(Expense::class, 'expense_category_id', 'id');
    }

    // Scopes
    public function scopeForCompany($query, $companyId)
    {
        return $query->where('company_id', $companyId);
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeOfType($query, $type)
    {
        return $query->where('type', $type);
    }

    public function scopeRoot($query)
    {
        return $query->whereNull('parent_id');
    }

    public function scopeOrdered($query)
    {
        return $query->orderBy('sort_order')->orderBy('name');
    }

    // Computed properties
    public function getFormattedAmountAttribute()
    {
        return number_format($this->amount, 2, '.', ',');
    }

    public function getHasChildrenAttribute()
    {
        return $this->children()->exists();
    }

    public function getFullPathAttribute()
    {
        $path = [$this->name];
        $parent = $this->parent;

        while ($parent) {
            array_unshift($path, $parent->name);
            $parent = $parent->parent;
        }

        return implode(' > ', $path);
    }

    // Business logic methods
    public function canBeDeleted()
    {
        return ! $this->expenses()->exists() && ! $this->children()->exists();
    }

    public function isReimbursementCategory()
    {
        return $this->type === 'reimbursement';
    }

    public function isExpenseCategory()
    {
        return $this->type === 'expense';
    }

    // Save hook to generate category code
    protected static function booted()
    {
        static::creating(function ($category) {
            if (empty($category->code)) {
                // Generate unique category code based on name
                $baseCode = strtoupper(substr(preg_replace('/[^a-zA-Z0-9]/', '', $category->name), 0, 3));
                $code = $baseCode;
                $counter = 1;

                while (static::where('company_id', $category->company_id)
                    ->where('code', $code)
                    ->exists()) {
                    $code = $baseCode.str_pad($counter, 2, '0', STR_PAD_LEFT);
                    $counter++;
                }

                $category->code = $code;
            }
        });
    }
}
