<?php

namespace App\Modules\Accounting\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\DB;

class CreditNote extends Model
{
    use HasFactory, HasUuids, SoftDeletes;

    protected $connection = 'pgsql';
    protected $table = 'acct.credit_notes';
    protected $keyType = 'string';
    public $incrementing = false;

    protected $fillable = [
        'company_id',
        'customer_id',
        'invoice_id',
        'credit_note_number',
        'credit_date',
        'amount',
        'base_currency',
        'reason',
        'status',
        'notes',
        'terms',
        'sent_at',
        'posted_at',
        'voided_at',
        'cancellation_reason',
        'transaction_id',
        'journal_entry_id',
        'created_by_user_id',
        'updated_by_user_id',
    ];

    protected $casts = [
        'company_id' => 'string',
        'customer_id' => 'string',
        'invoice_id' => 'string',
        'credit_date' => 'date',
        'amount' => 'decimal:2',
        'sent_at' => 'datetime',
        'posted_at' => 'datetime',
        'voided_at' => 'datetime',
        'transaction_id' => 'string',
        'created_by_user_id' => 'string',
        'updated_by_user_id' => 'string',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
        'deleted_at' => 'datetime',
    ];

    public function customer()
    {
        return $this->belongsTo(Customer::class, 'customer_id');
    }

    public function invoice()
    {
        return $this->belongsTo(Invoice::class, 'invoice_id');
    }

    public function company()
    {
        return $this->belongsTo(\App\Models\Company::class, 'company_id');
    }

    public function items()
    {
        return $this->hasMany(CreditNoteItem::class, 'credit_note_id');
    }

    public function applications()
    {
        return $this->hasMany(CreditNoteApplication::class, 'credit_note_id');
    }

    public function transaction()
    {
        return $this->belongsTo(Transaction::class, 'transaction_id');
    }

    /**
     * Generate a credit note number scoped per company (simple incremental suffix).
     */
    public static function generateCreditNoteNumber(string $companyId): string
    {
        $last = DB::connection('pgsql')->table('acct.credit_notes')
            ->where('company_id', $companyId)
            ->whereNotNull('credit_note_number')
            ->orderByDesc('created_at')
            ->value('credit_note_number');

        $base = 'CN-';
        $next = 1;

        if ($last && preg_match('/(\\d+)$/', $last, $m)) {
            $next = (int) $m[1] + 1;
        }

        return $base . str_pad((string) $next, 5, '0', STR_PAD_LEFT);
    }
}
