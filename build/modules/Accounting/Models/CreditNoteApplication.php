<?php

namespace App\Modules\Accounting\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class CreditNoteApplication extends Model
{
    use HasFactory, HasUuids;

    protected $connection = 'pgsql';
    protected $table = 'acct.credit_note_applications';
    protected $keyType = 'string';
    public $incrementing = false;

    protected $fillable = [
        'company_id',
        'credit_note_id',
        'invoice_id',
        'amount_applied',
        'invoice_balance_before',
        'invoice_balance_after',
        'applied_at',
        'user_id',
        'notes',
    ];

    protected $casts = [
        'company_id' => 'string',
        'credit_note_id' => 'string',
        'invoice_id' => 'string',
        'amount_applied' => 'decimal:2',
        'invoice_balance_before' => 'decimal:2',
        'invoice_balance_after' => 'decimal:2',
        'applied_at' => 'datetime',
        'user_id' => 'string',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    public function creditNote()
    {
        return $this->belongsTo(CreditNote::class, 'credit_note_id');
    }

    public function invoice()
    {
        return $this->belongsTo(Invoice::class, 'invoice_id');
    }
}
