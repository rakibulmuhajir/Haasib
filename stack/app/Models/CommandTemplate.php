<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CommandTemplate extends Model
{
    protected $fillable = [
        'command_id',
        'user_id',
        'name',
        'parameter_values',
        'is_shared',
    ];

    protected $casts = [
        'parameter_values' => 'array',
        'is_shared' => 'boolean',
    ];

    public function command(): BelongsTo
    {
        return $this->belongsTo(Command::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function scopeShared($query)
    {
        return $query->where('is_shared', true);
    }

    public function scopePrivate($query)
    {
        return $query->where('is_shared', false);
    }

    public function scopeForUser($query, $userId)
    {
        return $query->where('user_id', $userId);
    }

    public function scopeAccessibleBy($query, User $user)
    {
        return $query->where(function ($q) use ($user) {
            $q->where('user_id', $user->id)
                ->orWhere('is_shared', true);
        });
    }

    public function isAccessibleBy(User $user): bool
    {
        return $this->user_id === $user->id || $this->is_shared;
    }
}
