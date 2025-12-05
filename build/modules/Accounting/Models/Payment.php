<?php

namespace App\Modules\Accounting\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\DB;

class Payment extends Model
{
    use HasFactory, HasUuids, SoftDeletes;

    protected $connection = 'pgsql';
    protected $table = 'acct.payments';
    protected $keyType = 'string';
    public $incrementing = false;

    protected $fillable = [
        'company_id',
        'customer_id',
        'payment_number',
        'payment_date',
        'amount',
        'currency',
        'exchange_rate',
        'base_currency',
        'base_amount',
        'payment_method',
        'reference_number',
        'notes',
        'created_by_user_id',
        'updated_by_user_id',
    ];

    protected $casts = [
        'company_id' => 'string',
        'customer_id' => 'string',
        'payment_date' => 'date',
        'amount' => 'decimal:6',
        'exchange_rate' => 'decimal:8',
        'base_amount' => 'decimal:2',
        'base_currency' => 'string',
        'created_by_user_id' => 'string',
        'updated_by_user_id' => 'string',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
        'deleted_at' => 'datetime',
    ];

    public function paymentAllocations()
    {
        return $this->hasMany(PaymentAllocation::class, 'payment_id');
    }

    /**
     * Generate a payment number scoped per company (simple incremental suffix).
     */
    public static function generatePaymentNumber(string $companyId): string
    {
        $last = DB::table('acct.payments')
            ->where('company_id', $companyId)
            ->whereNotNull('payment_number')
            ->orderByDesc('created_at')
            ->value('payment_number');

        $base = 'PAY-';
        $next = 1;

        if ($last && preg_match('/(\\d+)$/', $last, $m)) {
            $next = (int) $m[1] + 1;
        }

        return $base . str_pad((string) $next, 5, '0', STR_PAD_LEFT);
    }
}
