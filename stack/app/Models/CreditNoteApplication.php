<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CreditNoteApplication extends Model
{
    use HasFactory, HasUuids;

    public $incrementing = false;

    protected $keyType = 'string';

    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'invoicing.credit_note_applications';

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'credit_note_id',
        'invoice_id',
        'amount_applied',
        'applied_at',
        'user_id',
        'notes',
        'invoice_balance_before',
        'invoice_balance_after',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'applied_at' => 'datetime',
            'amount_applied' => 'decimal:2',
            'invoice_balance_before' => 'decimal:2',
            'invoice_balance_after' => 'decimal:2',
            'credit_note_id' => 'string',
            'invoice_id' => 'string',
            'user_id' => 'string',
        ];
    }

    /**
     * Get the credit note that was applied.
     */
    public function creditNote(): BelongsTo
    {
        return $this->belongsTo(CreditNote::class);
    }

    /**
     * Get the invoice the credit note was applied to.
     */
    public function invoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class);
    }

    /**
     * Get the user who applied the credit note.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
