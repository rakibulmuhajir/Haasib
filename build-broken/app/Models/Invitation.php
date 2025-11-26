<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class Invitation extends Model
{
    use HasFactory, HasUuids;

    /**
     * The table associated with the model.
     */
    protected $table = 'auth.invitations';

    /**
     * The attributes that are mass assignable.
     */
    protected $fillable = [
        'company_id',
        'inviter_user_id', 
        'email',
        'role',
        'token',
        'expires_at',
        'accepted_at',
        'declined_at',
        'status',
        'metadata',
    ];

    /**
     * The attributes that should be cast.
     */
    protected $casts = [
        'expires_at' => 'datetime',
        'accepted_at' => 'datetime', 
        'declined_at' => 'datetime',
        'metadata' => 'array',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    /**
     * The attributes that should be hidden for serialization.
     */
    protected $hidden = [
        'token',
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
        return $this->belongsTo(User::class, 'inviter_user_id');
    }

    /**
     * Check if the invitation is pending.
     */
    public function isPending(): bool
    {
        return $this->status === 'pending' && $this->expires_at->isFuture();
    }

    /**
     * Check if the invitation has expired.
     */
    public function isExpired(): bool
    {
        return $this->expires_at->isPast();
    }

    /**
     * Check if the invitation is accepted.
     */
    public function isAccepted(): bool
    {
        return $this->status === 'accepted';
    }

    /**
     * Check if the invitation is declined.
     */
    public function isDeclined(): bool
    {
        return $this->status === 'declined';
    }

    /**
     * Accept the invitation.
     */
    public function accept(User $user = null): bool
    {
        if (!$this->isPending()) {
            return false;
        }

        $this->update([
            'status' => 'accepted',
            'accepted_at' => now(),
        ]);

        // Create company-user relationship
        if ($user) {
            $user->companies()->attach($this->company_id, [
                'id' => Str::uuid(),
                'role' => $this->role,
                'invited_by_user_id' => $this->inviter_user_id,
                'joined_at' => now(),
                'is_active' => true,
            ]);
        }

        return true;
    }

    /**
     * Decline the invitation.
     */
    public function decline(): bool
    {
        if (!$this->isPending()) {
            return false;
        }

        $this->update([
            'status' => 'declined',
            'declined_at' => now(),
        ]);

        return true;
    }

    /**
     * Mark invitation as expired.
     */
    public function markAsExpired(): bool
    {
        if ($this->status !== 'pending') {
            return false;
        }

        $this->update(['status' => 'expired']);
        return true;
    }

    /**
     * Generate a secure token for the invitation.
     */
    public static function generateToken(): string
    {
        return Str::random(64);
    }

    /**
     * Scope for pending invitations.
     */
    public function scopePending($query)
    {
        return $query->where('status', 'pending')
                    ->where('expires_at', '>', now());
    }

    /**
     * Scope for expired invitations.
     */
    public function scopeExpired($query)
    {
        return $query->where('status', 'pending')
                    ->where('expires_at', '<=', now());
    }

    /**
     * Scope for a specific email.
     */
    public function scopeForEmail($query, string $email)
    {
        return $query->where('email', $email);
    }

    /**
     * Scope for a specific company.
     */
    public function scopeForCompany($query, string $companyId)
    {
        return $query->where('company_id', $companyId);
    }

    /**
     * Get the URL for accepting the invitation.
     */
    public function getAcceptUrlAttribute(): string
    {
        return url("/invitations/accept/{$this->token}");
    }

    /**
     * Get the URL for declining the invitation.
     */
    public function getDeclineUrlAttribute(): string
    {
        return url("/invitations/decline/{$this->token}");
    }

    /**
     * Boot method to handle model events.
     */
    protected static function boot()
    {
        parent::boot();

        // Auto-generate token when creating
        static::creating(function ($invitation) {
            if (empty($invitation->token)) {
                $invitation->token = static::generateToken();
            }
            
            // Set default expiration (7 days from now)
            if (empty($invitation->expires_at)) {
                $invitation->expires_at = now()->addDays(7);
            }
        });
    }
}
