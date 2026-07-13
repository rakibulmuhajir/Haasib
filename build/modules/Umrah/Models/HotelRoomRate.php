<?php

namespace App\Modules\Umrah\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class HotelRoomRate extends Model
{
    use HasUuids, SoftDeletes;

    public const TYPES = ['sharing' => 'Sharing', 'double' => 'Double', 'triple' => 'Triple', 'quad' => 'Quad', 'quint' => 'Quint'];

    public const BED_COUNTS = ['sharing' => 1, 'double' => 2, 'triple' => 3, 'quad' => 4, 'quint' => 5];

    public static function bedsFor(string $roomType): int
    {
        return self::BED_COUNTS[$roomType] ?? 0;
    }

    protected $connection = 'pgsql';

    protected $table = 'umrah.hotel_room_rates';

    protected $keyType = 'string';

    public $incrementing = false;

    protected $fillable = ['company_id', 'hotel_id', 'room_type', 'retail_amount', 'cost_amount', 'is_active'];

    protected $casts = ['company_id' => 'string', 'hotel_id' => 'string', 'retail_amount' => 'decimal:2', 'cost_amount' => 'decimal:2', 'is_active' => 'boolean', 'created_at' => 'datetime', 'updated_at' => 'datetime', 'deleted_at' => 'datetime'];

    public function hotel(): BelongsTo
    {
        return $this->belongsTo(Hotel::class);
    }
}
