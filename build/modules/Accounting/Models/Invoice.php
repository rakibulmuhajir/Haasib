<?php

namespace App\Modules\Accounting\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\DB;

class Invoice extends Model
{
    use HasFactory, HasUuids, SoftDeletes;

    protected $connection = 'pgsql';
    protected $table = 'acct.invoices';
    protected $keyType = 'string';
    public $incrementing = false;

    protected $fillable = [
        'company_id',
        'customer_id',
        'invoice_number',
        'issue_date',
        'invoice_date',
        'due_date',
        'status',
        'currency',
        'base_currency',
        'exchange_rate',
        'subtotal',
        'tax_amount',
        'discount_amount',
        'total_amount',
        'paid_amount',
        'balance',
        'balance_due',
        'base_amount',
        'payment_terms',
        'notes',
        'internal_notes',
        'sent_at',
        'viewed_at',
        'paid_at',
        'voided_at',
        'recurring_schedule_id',
        'created_by_user_id',
        'updated_by_user_id',
        'payment_status',
    ];

    protected $casts = [
        'company_id' => 'string',
        'customer_id' => 'string',
        'recurring_schedule_id' => 'string',
        'issue_date' => 'date',
        'invoice_date' => 'date',
        'due_date' => 'date',
        'subtotal' => 'decimal:6',
        'tax_amount' => 'decimal:6',
        'discount_amount' => 'decimal:6',
        'total_amount' => 'decimal:6',
        'paid_amount' => 'decimal:6',
        'balance' => 'decimal:6',
        'balance_due' => 'decimal:6',
        'base_amount' => 'decimal:2',
        'exchange_rate' => 'decimal:8',
        'payment_terms' => 'integer',
        'sent_at' => 'datetime',
        'viewed_at' => 'datetime',
        'paid_at' => 'datetime',
        'voided_at' => 'datetime',
        'created_by_user_id' => 'string',
        'updated_by_user_id' => 'string',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
        'deleted_at' => 'datetime',
    ];

    public function lineItems()
    {
        return $this->hasMany(InvoiceLineItem::class, 'invoice_id');
    }

    /**
     * Generate an invoice number scoped per company (simple incremental suffix).
     */
    public static function generateInvoiceNumber(string $companyId): string
    {
        $last = DB::table('acct.invoices')
            ->where('company_id', $companyId)
            ->whereNotNull('invoice_number')
            ->orderByDesc('created_at')
            ->value('invoice_number');

        $base = 'INV-';
        $next = 1;

        if ($last && preg_match('/(\d+)$/' , $last, $m)) {
            $next = (int) $m[1] + 1;
        }

        return $base . str_pad((string) $next, 5, '0', STR_PAD_LEFT);
    }
}
