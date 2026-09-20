<?php

namespace App\Modules\Accounting\Models;

use App\Models\Company;
use App\Models\User;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One document attached to a posted transaction — the bill behind an expense.
 * Stored on a private disk and served only through a company-scoped controller.
 */
class TransactionAttachment extends Model
{
    use HasUuids;

    protected $connection = 'pgsql';
    protected $table = 'acct.transaction_attachments';
    protected $keyType = 'string';
    public $incrementing = false;

    protected $fillable = [
        'company_id',
        'transaction_id',
        'disk',
        'path',
        'original_name',
        'mime_type',
        'size_bytes',
        'uploaded_by_user_id',
    ];

    protected $casts = [
        'size_bytes' => 'integer',
    ];

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function transaction(): BelongsTo
    {
        return $this->belongsTo(Transaction::class);
    }

    public function uploadedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by_user_id');
    }
}
