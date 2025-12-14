<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CompanyInvitation extends Model
{
    use HasFactory, HasUuids;

    protected $connection = 'pgsql';

    protected $table = 'auth.company_invitations';

    protected $keyType = 'string';

    public $incrementing = false;

    protected $fillable = [
        'company_id',
        'email',
        'role',
        'token',
        'expires_at',
        'invited_by_user_id',
        'accepted_by_user_id',
        'status',
        'accepted_at',
    ];

    protected $casts = [
        'expires_at' => 'datetime',
        'accepted_at' => 'datetime',
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
     * Get the user who accepted the invitation.
     */
    public function acceptedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'accepted_by_user_id');
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
        return $this->status === 'pending' && ! $this->isExpired();
    }

    /**
     * Mark invitation as accepted.
     */
    public function accept(User $user): void
    {
        $this->status = 'accepted';
        $this->accepted_by_user_id = $user->id;
        $this->accepted_at = now();
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
        return $this->status === 'pending' && ! $this->isExpired();
    }
}
