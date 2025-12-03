<?php

namespace App\Modules\Accounting\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

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
        'created_by_user_id' => 'string',
        'updated_by_user_id' => 'string',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
        'deleted_at' => 'datetime',
    ];
}
