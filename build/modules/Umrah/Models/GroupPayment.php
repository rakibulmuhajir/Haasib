<?php

namespace App\Modules\Umrah\Models;

use App\Models\Company;
use App\Modules\Accounting\Models\Account;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class GroupPayment extends Model
{
    use HasFactory, HasUuids, SoftDeletes;

    protected $connection = 'pgsql';

    protected $table = 'umrah.group_payments';

    protected $keyType = 'string';

    public $incrementing = false;

    public const DIRECTION_RECEIVED = 'received';

    public const DIRECTION_SENT = 'sent';

    public const DIRECTIONS = [self::DIRECTION_RECEIVED => 'Received', self::DIRECTION_SENT => 'Sent'];

    public const METHOD_CASH = 'cash';

    public const METHOD_BANK_TRANSFER = 'bank_transfer';

    public const METHOD_CARD = 'card';

    public const METHOD_WALLET = 'wallet';

    public const METHOD_OTHER = 'other';

    public const METHODS = [
        self::METHOD_CASH => 'Cash',
        self::METHOD_BANK_TRANSFER => 'Bank transfer',
        self::METHOD_CARD => 'Card',
        self::METHOD_WALLET => 'Wallet',
        self::METHOD_OTHER => 'Other',
    ];

    protected $fillable = [
        'company_id',
        'visa_group_id',
        'agent_id',
        'direction',
        'visa_vendor_id',
        'hotel_vendor_id',
        'account_id',
        'payment_number',
        'payment_date',
        'amount',
        'currency',
        'exchange_rate',
        'base_currency',
        'base_amount',
        'method',
        'reference',
        'notes',
        'transaction_id',
    ];

    protected $casts = [
        'company_id' => 'string',
        'visa_group_id' => 'string',
        'agent_id' => 'string',
        'visa_vendor_id' => 'string',
        'hotel_vendor_id' => 'string',
        'account_id' => 'string',
        'payment_date' => 'date',
        'amount' => 'decimal:6',
        'exchange_rate' => 'decimal:8',
        'base_amount' => 'decimal:2',
        'transaction_id' => 'string',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
        'deleted_at' => 'datetime',
    ];

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function group(): BelongsTo
    {
        return $this->belongsTo(VisaGroup::class, 'visa_group_id');
    }

    public function agent(): BelongsTo
    {
        return $this->belongsTo(Agent::class);
    }

    public function account(): BelongsTo
    {
        return $this->belongsTo(Account::class);
    }

    public function visaVendor(): BelongsTo
    {
        return $this->belongsTo(VisaVendor::class);
    }

    public function hotelVendor(): BelongsTo
    {
        return $this->belongsTo(HotelVendor::class);
    }

    public function transaction(): BelongsTo
    {
        return $this->belongsTo(\App\Modules\Accounting\Models\Transaction::class);
    }

    public function allocations(): HasMany
    {
        return $this->hasMany(PaymentAllocation::class, 'group_payment_id');
    }
}
