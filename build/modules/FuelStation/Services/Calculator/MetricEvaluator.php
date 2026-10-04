<?php

namespace App\Modules\FuelStation\Services\Calculator;

/**
 * One Calculator metric. An evaluator never does accounting of its own: it asks the service that
 * builds the matching report and returns that report's figure, so a value always equals its report.
 */
abstract class MetricEvaluator
{
    public const COLLECTIONS = ['product', 'fuel', 'all_fuels', 'all_products', 'account', 'customer', 'channel', 'none'];

    /**
     * @param  array<int,string>  $collections  What it accepts; the first is the default shown in the builder.
     */
    public function __construct(
        public readonly string $key,
        public readonly string $label,
        public readonly string $group,
        public readonly string $unit,
        public readonly array $collections,
        public readonly bool $takesDay = false,
    ) {}

    /** The unit of this figure for the chosen collection (litres vs pieces, say). */
    public function unitFor(CalculatorContext $c, array $collection): string
    {
        return $this->unit;
    }

    /**
     * @param  array{type:string,id?:string}  $collection
     * @param  string  $from  A single-day metric receives the same date as $from and $to.
     */
    abstract public function evaluate(CalculatorContext $c, array $collection, string $from, string $to): MetricResult;

    public function describe(): array
    {
        return [
            'key' => $this->key,
            'label' => $this->label,
            'group' => $this->group,
            'unit' => $this->unit,
            'collections' => $this->collections,
            'takes_day' => $this->takesDay,
        ];
    }
}
