<?php

namespace App\Modules\Umrah\Models;

use App\Models\Company;
use App\Models\User;
use App\Modules\Accounting\Models\Bill;
use App\Modules\Accounting\Models\Customer;
use App\Modules\Accounting\Models\Invoice;
use App\Modules\Accounting\Models\Vendor;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * A booking is a container: one buyer invoice, one supplier bill, and
 * the tickets that make up the sale, all created inside a single
 * transaction so the supplier-clearing account can never be left
 * holding a residual.
 */
class TicketBooking extends Model
{
    use HasUuids, SoftDeletes;

    protected $connection = 'pgsql';

    protected $table = 'umrah.ticket_bookings';

    protected $keyType = 'string';

    public $incrementing = false;

    protected $fillable = [
        'company_id',
        'customer_id',
        'agent_id',
        'supplier_vendor_id',
        'invoice_id',
        'bill_id',
        'booking_reference',
        'pnr',
        'booking_date',
        'status',
        'idempotency_key',
        'created_by_user_id',
    ];

    protected $casts = [
        'company_id' => 'string',
        'customer_id' => 'string',
        'agent_id' => 'string',
        'supplier_vendor_id' => 'string',
        'invoice_id' => 'string',
        'bill_id' => 'string',
        'created_by_user_id' => 'string',
        'booking_date' => 'date',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
        'deleted_at' => 'datetime',
    ];

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function agent(): BelongsTo
    {
        return $this->belongsTo(Agent::class);
    }

    public function supplierVendor(): BelongsTo
    {
        return $this->belongsTo(Vendor::class, 'supplier_vendor_id');
    }

    public function invoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class);
    }

    public function bill(): BelongsTo
    {
        return $this->belongsTo(Bill::class);
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }

    public function tickets(): HasMany
    {
        return $this->hasMany(Ticket::class);
    }
}
