<?php

namespace App\Modules\Umrah\Models;

use App\Modules\Accounting\Models\Transaction;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PaymentAllocation extends Model
{
    use HasUuids;

    protected $connection = 'pgsql';

    protected $table = 'umrah.payment_allocations';

    protected $keyType = 'string';

    public $incrementing = false;

    protected $fillable = ['company_id', 'group_payment_id', 'visa_group_id', 'base_amount', 'transaction_id'];

    protected $casts = ['company_id' => 'string', 'group_payment_id' => 'string', 'visa_group_id' => 'string', 'base_amount' => 'decimal:2', 'transaction_id' => 'string'];

    public function payment(): BelongsTo
    {
        return $this->belongsTo(GroupPayment::class, 'group_payment_id');
    }

    public function group(): BelongsTo
    {
        return $this->belongsTo(VisaGroup::class, 'visa_group_id');
    }

    public function transaction(): BelongsTo
    {
        return $this->belongsTo(Transaction::class);
    }
}
