<?php

namespace App\Modules\FuelStation\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** A saved Calculator formula (fuel.calculator_formulas): personal, or shared company-wide. */
class CalculatorFormula extends Model
{
    use HasUuids;

    protected $connection = 'pgsql';
    protected $table = 'fuel.calculator_formulas';
    protected $keyType = 'string';
    public $incrementing = false;

    protected $fillable = ['company_id', 'user_id', 'name', 'formula', 'is_shared'];

    protected $casts = [
        'company_id' => 'string',
        'user_id' => 'string',
        'formula' => 'array',
        'is_shared' => 'boolean',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
