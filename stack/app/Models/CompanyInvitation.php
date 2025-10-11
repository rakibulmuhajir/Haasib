<?php

namespace App\Models;

use App\Enums\CompanyRole;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CompanyInvitation extends Model
{
    protected $fillable = [
        'company_id',
        'email',
        'role',
        'token',
        'message',
        'expires_at',
        'invited_by_user_id',
        'status',
    ];

    protected $casts = [
        'expires_at' => 'datetime',
        'status' => 'string',
    ];

    /**
     * Get the company that owns the invitation.
     */
    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    /**
     * Get the user who sent the invitation.
     */
    public function inviter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'invited_by_user_id');
    }

    /**
     * Check if the invitation is expired.
     */
    public function isExpired(): bool
    {
        return $this->expires_at->isPast();
    }

    /**
     * Check if the invitation is still valid.
     */
    public function isValid(): bool
    {
        return $this->status === 'pending' && !$this->isExpired();
    }

    /**
     * Mark invitation as accepted.
     */
    public function accept(): void
    {
        $this->status = 'accepted';
        $this->save();
    }

    /**
     * Mark invitation as rejected.
     */
    public function reject(): void
    {
        $this->status = 'rejected';
        $this->save();
    }

    /**
     * Check if the invitation can be revoked.
     */
    public function canBeRevoked(): bool
    {
        return $this->status === 'pending' && !$this->isExpired();
    }
}
