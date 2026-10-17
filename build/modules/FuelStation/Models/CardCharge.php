<?php

namespace App\Modules\FuelStation\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

/** A card charge the bank or card supplier kept, entered by hand; see CardChargeService. Deleted = undone. */
class CardCharge extends Model
{
    use HasUuids, SoftDeletes;

    protected $connection = 'pgsql';

    protected $table = 'fuel.card_charges';

    protected $keyType = 'string';

    public $incrementing = false;

    protected $fillable = [
        'company_id', 'channel_code', 'channel_label', 'settles_to', 'charge_date', 'amount',
        'reference', 'notes', 'transaction_id', 'bill_id', 'vendor_id', 'created_by_user_id',
    ];

    protected $casts = [
        'charge_date' => 'date',
        'amount' => 'decimal:2',
    ];
}
