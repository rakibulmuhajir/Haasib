<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CommandHistory extends Model
{
    protected $fillable = [
        'user_id',
        'command_id',
        'company_id',
        'executed_at',
        'input_text',
        'parameters_used',
        'execution_status',
        'result_summary',
        'audit_reference',
    ];

    protected $casts = [
        'executed_at' => 'datetime',
        'parameters_used' => 'array',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function command(): BelongsTo
    {
        return $this->belongsTo(Command::class);
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function scopeForUser($query, $userId)
    {
        return $query->where('user_id', $userId);
    }

    public function scopeForCompany($query, $companyId)
    {
        return $query->where('company_id', $companyId);
    }

    public function scopeSuccessful($query)
    {
        return $query->where('execution_status', 'success');
    }

    public function scopeFailed($query)
    {
        return $query->where('execution_status', 'failed');
    }

    public function scopeRecent($query, int $days = 7)
    {
        return $query->where('executed_at', '>=', now()->subDays($days));
    }

    public function isSuccessful(): bool
    {
        return $this->execution_status === 'success';
    }

    public function isFailed(): bool
    {
        return $this->execution_status === 'failed';
    }
}
