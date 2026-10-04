<?php

namespace App\Modules\FuelStation\Services\Calculator;

/** One metric's figure: the value (null when the books have none), its unit, the report it came from. */
final class MetricResult
{
    public function __construct(
        public readonly ?float $value,
        public readonly ?string $unit,
        public readonly ?string $href = null,
        public readonly ?string $note = null,
    ) {}
}
