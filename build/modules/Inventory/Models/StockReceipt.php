<?php

namespace App\Modules\Inventory\Models;

use App\Models\Company;
use App\Models\User;
use App\Modules\Accounting\Models\Bill;
use App\Modules\Accounting\Models\Transaction;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class StockReceipt extends Model
{
    use HasFactory, HasUuids;

    protected $connection = 'pgsql';
    protected $table = 'inv.stock_receipts';
    protected $keyType = 'string';
    public $incrementing = false;

    protected $fillable = [
        'company_id',
        'bill_id',
        'receipt_date',
        'notes',
        'variance_transaction_id',
        'created_by_user_id',
    ];

    protected $casts = [
        'company_id' => 'string',
        'bill_id' => 'string',
        'receipt_date' => 'date',
        'variance_transaction_id' => 'string',
        'created_by_user_id' => 'string',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class, 'company_id');
    }

    public function bill(): BelongsTo
    {
        return $this->belongsTo(Bill::class, 'bill_id');
    }

    public function varianceTransaction(): BelongsTo
    {
        return $this->belongsTo(Transaction::class, 'variance_transaction_id');
    }

    public function lines(): HasMany
    {
        return $this->hasMany(StockReceiptLine::class, 'stock_receipt_id');
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }
}
