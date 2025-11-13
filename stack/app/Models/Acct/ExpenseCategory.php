<?php

namespace App\Models\Acct;

use App\Models\Concerns\BelongsToCompany;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\SoftDeletes;
use App\Traits\AuditLogging;
use App\Models\User;
use App\Models\Company;
use App\Models\Expense;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;
use Illuminate\Database\Eloquent\Collection;

class ExpenseCategory extends Model
{
    use HasFactory, HasUuids, BelongsToCompany, SoftDeletes, AuditLogging;

    protected $table = 'acct.expense_categories';

    protected $fillable = [
        'company_id',
        'name',
        'code',
        'description',
        'type',
        'is_active',
        'parent_id',
        'color',
        'sort_order',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'sort_order' => 'integer',
        'deleted_at' => 'datetime',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    protected $hidden = [
        'updated_by',
    ];

    protected $appends = [
        'has_children',
        'full_path',
        'is_root',
        'is_leaf',
        'total_expenses_count',
    ];

    // UUID Configuration
    protected $keyType = 'string';
    public $incrementing = false;

    // === CONSTANTS ===

    const TYPE_EXPENSE = 'expense';
    const TYPE_REIMBURSEMENT = 'reimbursement';
    const TYPE_CAPITAL = 'capital';
    const TYPE_OPERATING = 'operating';

    // === RELATIONSHIPS ===

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class, 'company_id');
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(ExpenseCategory::class, 'parent_id');
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function updatedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    public function children(): HasMany
    {
        return $this->hasMany(ExpenseCategory::class, 'parent_id');
    }

    public function descendants(): HasMany
    {
        return $this->children()->with('descendants');
    }

    public function expenses(): HasMany
    {
        return $this->hasMany(Expense::class, 'expense_category_id');
    }

    public function allExpenses(): HasMany
    {
        return $this->hasMany(Expense::class, 'expense_category_id')
                    ->orWhereHas('category', function ($query) {
                        $query->where('parent_id', $this->id);
                    });
    }

    // === SCOPES ===

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    public function scopeInactive(Builder $query): Builder
    {
        return $query->where('is_active', false);
    }

    public function scopeOfType(Builder $query, string $type): Builder
    {
        return $query->where('type', $type);
    }

    public function scopeRoot(Builder $query): Builder
    {
        return $query->whereNull('parent_id');
    }

    public function scopeWithParent(Builder $query): Builder
    {
        return $query->whereNotNull('parent_id');
    }

    public function scopeOrdered(Builder $query): Builder
    {
        return $query->orderBy('sort_order')->orderBy('name');
    }

    public function scopeByType(Builder $query, string $type): Builder
    {
        return $query->where('type', $type);
    }

    public function scopeExpense(Builder $query): Builder
    {
        return $query->where('type', self::TYPE_EXPENSE);
    }

    public function scopeReimbursement(Builder $query): Builder
    {
        return $query->where('type', self::TYPE_REIMBURSEMENT);
    }

    public function scopeCapital(Builder $query): Builder
    {
        return $query->where('type', self::TYPE_CAPITAL);
    }

    public function scopeOperating(Builder $query): Builder
    {
        return $query->where('type', self::TYPE_OPERATING);
    }

    // === BUSINESS LOGIC METHODS ===

    public function isActive(): bool
    {
        return $this->is_active;
    }

    public function isRoot(): bool
    {
        return is_null($this->parent_id);
    }

    public function isLeaf(): bool
    {
        return $this->children()->count() === 0;
    }

    public function hasChildren(): bool
    {
        return $this->children()->exists();
    }

    public function canBeDeleted(): bool
    {
        return !$this->expenses()->exists() && !$this->children()->exists();
    }

    public function canHaveChildren(): bool
    {
        return $this->isLeaf();
    }

    public function isReimbursementCategory(): bool
    {
        return $this->type === self::TYPE_REIMBURSEMENT;
    }

    public function isExpenseCategory(): bool
    {
        return $this->type === self::TYPE_EXPENSE;
    }

    public function isCapitalCategory(): bool
    {
        return $this->type === self::TYPE_CAPITAL;
    }

    public function isOperatingCategory(): bool
    {
        return $this->type === self::TYPE_OPERATING;
    }

    public function getLevel(): int
    {
        $level = 0;
        $parent = $this->parent;

        while ($parent) {
            $level++;
            $parent = $parent->parent;
        }

        return $level;
    }

    public function getAncestors(): Collection
    {
        $ancestors = collect();
        $parent = $this->parent;

        while ($parent) {
            $ancestors->push($parent);
            $parent = $parent->parent;
        }

        return $ancestors;
    }

    public function getDescendants(): Collection
    {
        $descendants = collect();

        foreach ($this->children as $child) {
            $descendants->push($child);
            $descendants = $descendants->merge($child->getDescendants());
        }

        return $descendants;
    }

    public function getTreePath(): string
    {
        $path = collect([$this->name]);
        $parent = $this->parent;

        while ($parent) {
            $path->prepend($parent->name);
            $parent = $parent->parent;
        }

        return $path->implode(' > ');
    }

    // === MUTATORS & ACCESSORS ===

    public function getHasChildrenAttribute(): bool
    {
        return $this->hasChildren();
    }

    public function getIsRootAttribute(): bool
    {
        return $this->isRoot();
    }

    public function getIsLeafAttribute(): bool
    {
        return $this->isLeaf();
    }

    public function getFullPathAttribute(): string
    {
        return $this->getTreePath();
    }

    public function getTotalExpensesCountAttribute(): int
    {
        return $this->allExpenses()->count();
    }

    public function getNameAttribute(): string
    {
        return trim($this->attributes['name']);
    }

    public function setNameAttribute(string $value): void
    {
        $this->attributes['name'] = trim($value);
    }

    public function getCodeAttribute(): string
    {
        if (!isset($this->attributes['code'])) {
            $this->attributes['code'] = $this->generateCategoryCode();
            $this->save();
        }

        return $this->attributes['code'];
    }

    private function generateCategoryCode(): string
    {
        $baseCode = strtoupper(substr(preg_replace('/[^a-zA-Z0-9]/', '', $this->name), 0, 3));
        $code = $baseCode;
        $counter = 1;

        while (static::where('company_id', $this->company_id)
            ->where('code', $code)
            ->exists()) {
            $code = $baseCode . str_pad($counter, 2, '0', STR_PAD_LEFT);
            $counter++;
        }

        return $code;
    }

    public function activate(): bool
    {
        $this->is_active = true;
        return $this->save();
    }

    public function deactivate(): bool
    {
        // Don't deactivate if it has active children or expenses
        if ($this->children()->where('is_active', true)->exists() || 
            $this->expenses()->exists()) {
            throw new \InvalidArgumentException('Cannot deactivate category with active children or expenses');
        }

        $this->is_active = false;
        return $this->save();
    }

    // === EVENTS ===

    protected static function booted(): void
    {
        static::creating(function (ExpenseCategory $category) {
            if (Auth::check()) {
                $category->created_by = Auth::id();
                $category->updated_by = Auth::id();
            }

            // Generate code if not provided
            if (empty($category->code)) {
                $category->code = $category->generateCategoryCode();
            }
        });

        static::updating(function (ExpenseCategory $category) {
            if (Auth::check()) {
                $category->updated_by = Auth::id();
            }

            // Prevent circular reference
            if ($category->isDirty('parent_id') && $category->parent_id) {
                if ($category->id === $category->parent_id) {
                    throw new \InvalidArgumentException('Category cannot be its own parent');
                }

                // Check if creating circular reference
                if ($category->createsCircularReference($category->parent_id)) {
                    throw new \InvalidArgumentException('Cannot create circular reference in category hierarchy');
                }
            }
        });

        static::deleting(function (ExpenseCategory $category) {
            if (!$category->canBeDeleted()) {
                throw new \InvalidArgumentException('Cannot delete category with existing expenses or children');
            }
        });
    }

    private function createsCircularReference(string $potentialParentId): bool
    {
        $current = static::find($potentialParentId);

        while ($current) {
            if ($current->id === $this->id) {
                return true;
            }
            $current = $current->parent;
        }

        return false;
    }

    // === QUERY SCOPES ===

    public function scopeForCompany(Builder $query, string $companyId): Builder
    {
        return $query->where('company_id', $companyId);
    }

    public function scopeWithExpensesCount(Builder $query): Builder
    {
        return $query->withCount(['expenses', 'children']);
    }

    public function scopeSearch(Builder $query, string $term): Builder
    {
        return $query->where(function ($q) use ($term) {
            $q->where('name', 'like', "%{$term}%")
              ->orWhere('code', 'like', "%{$term}%")
              ->orWhere('description', 'like', "%{$term}%");
        });
    }

    // === STATIC METHODS ===

    public static function getTypes(): array
    {
        return [
            self::TYPE_EXPENSE => 'Expense',
            self::TYPE_REIMBURSEMENT => 'Reimbursement',
            self::TYPE_CAPITAL => 'Capital',
            self::TYPE_OPERATING => 'Operating',
        ];
    }

    public static function getActiveCategories(?string $companyId = null): Collection
    {
        $query = static::active()->ordered();

        if ($companyId) {
            $query->where('company_id', $companyId);
        }

        return $query->get();
    }

    public static function getTree(?string $companyId = null): Collection
    {
        $query = static::active()->ordered()->root();

        if ($companyId) {
            $query->where('company_id', $companyId);
        }

        return $query->with('children.children')->get();
    }
}
