<?php

namespace App\Modules\FuelStation\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

/** Tax a card company deducted from card sales; see CardTaxService. Deleted = undone. */
class CardTaxDeduction extends Model
{
    use HasUuids, SoftDeletes;

    protected $connection = 'pgsql';

    protected $table = 'fuel.card_tax_deductions';

    protected $keyType = 'string';

    public $incrementing = false;

    protected $fillable = [
        'company_id', 'channel_code', 'channel_label', 'settles_to', 'deduction_date', 'amount',
        'section', 'reference', 'notes', 'transaction_id', 'bill_id', 'vendor_id', 'created_by_user_id',
    ];

    protected $casts = [
        'deduction_date' => 'date',
        'amount' => 'decimal:2',
    ];
}
