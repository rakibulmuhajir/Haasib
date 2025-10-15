<?php

namespace Modules\Accounting\Domain\Customers\Models;

use App\Models\Customer as BaseCustomer;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class CustomerContact extends Model
{
    use SoftDeletes;

    protected $table = 'invoicing.customer_contacts';

    protected $fillable = [
        'customer_id',
        'company_id',
        'first_name',
        'last_name',
        'email',
        'phone',
        'role',
        'is_primary',
        'preferred_channel',
        'created_by_user_id',
    ];

    protected $casts = [
        'is_primary' => 'boolean',
        'deleted_at' => 'datetime',
    ];

    protected $dates = [
        'created_at',
        'updated_at',
        'deleted_at',
    ];

    /**
     * Get the customer that owns the contact.
     */
    public function customer()
    {
        return $this->belongsTo(BaseCustomer::class, 'customer_id');
    }

    /**
     * Get the company that owns the contact.
     */
    public function company()
    {
        return $this->belongsTo(\App\Models\Company::class, 'company_id');
    }

    /**
     * Get the user who created the contact.
     */
    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }

    /**
     * Scope to get primary contacts.
     */
    public function scopePrimary(Builder $query): Builder
    {
        return $query->where('is_primary', true);
    }

    /**
     * Scope to get contacts by role.
     */
    public function scopeByRole(Builder $query, string $role): Builder
    {
        return $query->where('role', $role);
    }

    /**
     * Scope to get contacts by preferred channel.
     */
    public function scopeByPreferredChannel(Builder $query, string $channel): Builder
    {
        return $query->where('preferred_channel', $channel);
    }

    /**
     * Scope to search contacts by name or email.
     */
    public function scopeSearch(Builder $query, string $search): Builder
    {
        return $query->where(function (Builder $q) use ($search) {
            $q->where('first_name', 'ILIKE', "%{$search}%")
                ->orWhere('last_name', 'ILIKE', "%{$search}%")
                ->orWhere('email', 'ILIKE', "%{$search}%");
        });
    }

    /**
     * Get the full name attribute.
     */
    public function getFullNameAttribute(): string
    {
        return trim("{$this->first_name} {$this->last_name}");
    }

    /**
     * Set as primary contact for the role.
     * This will unset other primary contacts for the same role.
     */
    public function setAsPrimary(): void
    {
        static::where('customer_id', $this->customer_id)
            ->where('role', $this->role)
            ->where('id', '!=', $this->id)
            ->update(['is_primary' => false]);

        $this->update(['is_primary' => true]);
    }

    /**
     * Get the primary contact for a specific role.
     */
    public static function getPrimaryForRole(BaseCustomer $customer, string $role): ?self
    {
        return static::where('customer_id', $customer->id)
            ->where('role', $role)
            ->primary()
            ->first();
    }

    /**
     * Check if email is unique for the customer.
     */
    public static function isEmailUniqueForCustomer(BaseCustomer $customer, string $email, ?self $excludeContact = null): bool
    {
        $query = static::where('customer_id', $customer->id)
            ->where('email', $email);

        if ($excludeContact) {
            $query->where('id', '!=', $excludeContact->id);
        }

        return ! $query->exists();
    }

    /**
     * Boot the model.
     */
    protected static function booted()
    {
        static::creating(function ($contact) {
            // Set company context from customer if not provided
            if (! $contact->company_id && $contact->customer_id) {
                $contact->company_id = BaseCustomer::find($contact->customer_id)?->company_id;
            }

            // Set current user as creator if not provided
            if (! $contact->created_by_user_id) {
                $contact->created_by_user_id = auth()->id();
            }
        });

        static::updating(function ($contact) {
            // If setting as primary, unset others
            if ($contact->is_primary && $contact->wasChanged('is_primary')) {
                static::where('customer_id', $contact->customer_id)
                    ->where('role', $contact->role)
                    ->where('id', '!=', $contact->id)
                    ->update(['is_primary' => false]);
            }
        });
    }
}
