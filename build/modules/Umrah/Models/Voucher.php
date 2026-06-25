<?php

namespace App\Modules\Umrah\Models;

use App\Models\Company;
use App\Models\User;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Voucher extends Model
{
    use HasFactory, HasUuids, SoftDeletes;

    public const STATUS_DRAFT = 'draft';
    public const STATUS_ISSUED = 'issued';
    public const STATUS_CANCELLED = 'cancelled';

    public const STATUSES = [
        self::STATUS_DRAFT => 'Draft',
        self::STATUS_ISSUED => 'Issued',
        self::STATUS_CANCELLED => 'Cancelled',
    ];

    public const AIRLINES = [
        'SV' => 'Saudia',
        'XY' => 'flynas',
        'F3' => 'flyadeal',
        'PK' => 'Pakistan International Airlines',
        'PA' => 'airblue',
        'PF' => 'AirSial',
        'ER' => 'SereneAir',
        '9P' => 'Fly Jinnah',
        'AI' => 'Air India',
        'IX' => 'Air India Express',
        '6E' => 'IndiGo',
        'SG' => 'SpiceJet',
        'BG' => 'Biman Bangladesh Airlines',
        'BS' => 'US-Bangla Airlines',
        'UL' => 'SriLankan Airlines',
        'RA' => 'Nepal Airlines',
        'H9' => 'Himalaya Airlines',
        'EK' => 'Emirates',
        'FZ' => 'flydubai',
        'G9' => 'Air Arabia',
        'QR' => 'Qatar Airways',
        'EY' => 'Etihad Airways',
        'GF' => 'Gulf Air',
        'WY' => 'Oman Air',
        'OV' => 'SalamAir',
        'KU' => 'Kuwait Airways',
        'J9' => 'Jazeera Airways',
        'TK' => 'Turkish Airlines',
    ];

    public const AIRPORT_CITIES = [
        'JED' => 'Jeddah',
        'MED' => 'Madinah',
        'RUH' => 'Riyadh',
        'DMM' => 'Dammam',
        'TIF' => 'Taif',
        'AHB' => 'Abha',
        'GIZ' => 'Jazan',
        'ELQ' => 'Al Qassim',
        'KHI' => 'Karachi',
        'LHE' => 'Lahore',
        'ISB' => 'Islamabad',
        'PEW' => 'Peshawar',
        'SKT' => 'Sialkot',
        'MUX' => 'Multan',
        'UET' => 'Quetta',
        'FSD' => 'Faisalabad',
        'DEL' => 'Delhi',
        'BOM' => 'Mumbai',
        'HYD' => 'Hyderabad',
        'LKO' => 'Lucknow',
        'CCJ' => 'Kozhikode',
        'COK' => 'Kochi',
        'MAA' => 'Chennai',
        'BLR' => 'Bengaluru',
        'AMD' => 'Ahmedabad',
        'SXR' => 'Srinagar',
        'DAC' => 'Dhaka',
        'CGP' => 'Chattogram',
        'CMB' => 'Colombo',
        'KTM' => 'Kathmandu',
        'DXB' => 'Dubai',
        'SHJ' => 'Sharjah',
        'AUH' => 'Abu Dhabi',
        'DOH' => 'Doha',
        'BAH' => 'Manama',
        'MCT' => 'Muscat',
        'KWI' => 'Kuwait City',
        'IST' => 'Istanbul',
        'LHR' => 'London Heathrow',
        'MAN' => 'Manchester',
        'BHX' => 'Birmingham',
        'JFK' => 'New York',
        'IAD' => 'Washington Dulles',
        'ORD' => 'Chicago O Hare',
    ];

    protected $connection = 'pgsql';
    protected $table = 'umrah.vouchers';
    protected $keyType = 'string';
    public $incrementing = false;

    protected $fillable = [
        'company_id',
        'visa_group_id',
        'agent_id',
        'voucher_number',
        'title',
        'status',
        'onward_airline',
        'onward_flight_number',
        'onward_departure_city',
        'onward_arrival_city',
        'onward_departure_at',
        'onward_arrival_at',
        'return_airline',
        'return_flight_number',
        'return_departure_city',
        'return_arrival_city',
        'return_departure_at',
        'return_arrival_at',
        'hotel_stays',
        'notes',
        'created_by_user_id',
    ];

    protected $casts = [
        'company_id' => 'string',
        'visa_group_id' => 'string',
        'agent_id' => 'string',
        'created_by_user_id' => 'string',
        'onward_departure_at' => 'datetime',
        'onward_arrival_at' => 'datetime',
        'return_departure_at' => 'datetime',
        'return_arrival_at' => 'datetime',
        'hotel_stays' => 'array',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
        'deleted_at' => 'datetime',
    ];

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function group(): BelongsTo
    {
        return $this->belongsTo(VisaGroup::class, 'visa_group_id')->withTrashed();
    }

    public function agent(): BelongsTo
    {
        return $this->belongsTo(Agent::class)->withTrashed();
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }

    public function voucherPassengers(): HasMany
    {
        return $this->hasMany(VoucherPassenger::class);
    }

    public function passengers(): BelongsToMany
    {
        return $this->belongsToMany(Passenger::class, 'umrah.voucher_passengers')
            ->withPivot(['company_id', 'visa_group_id'])
            ->withTimestamps();
    }
}
