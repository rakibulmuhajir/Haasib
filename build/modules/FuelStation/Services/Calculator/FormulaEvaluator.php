<?php

namespace App\Modules\FuelStation\Services\Calculator;

use Carbon\CarbonInterface;

/**
 * Works out a Calculator formula -- a JSON tree, never a string to eval -- against the books.
 *
 * node = {type:'value', metric, collection:{type,id?}, when:{...}}
 *      | {type:'number', value}
 *      | {type:'op', op:'+'|'-'|'*'|'/', left, right}
 *      | {type:'group', inner}
 *
 * Nothing is written anywhere. Units follow the arithmetic (Rs / L is Rs/L); adding or taking away
 * two different units still computes but warns; dividing by zero gives no result, not a guess.
 */
class FormulaEvaluator
{
    public const MAX_VALUES = 25;

    public const MAX_NODES = 200;

    public const MAX_DEPTH = 60;

    private const OPS = ['+', '-', '*', '/'];

    public function __construct(private readonly MetricCatalog $catalog) {}

    /** The reason a formula cannot be run (shape, size, an unknown metric ...), or null when it can. */
    public function problem(mixed $ast): ?string
    {
        if (! is_array($ast)) {
            return 'Add something to calculate.';
        }
        $nodes = 0;
        $values = 0;

        $error = $this->check($ast, 1, $nodes, $values);
        if ($error !== null) {
            return $error;
        }

        return $values > self::MAX_VALUES ? 'At most '.self::MAX_VALUES.' values in one formula.' : null;
    }

    /**
     * @return array{result:?float,unit:?string,message:?string,warnings:array<int,string>,parts:array<int,array<string,mixed>>}
     */
    public function evaluate(CalculatorContext $context, array $ast, ?CarbonInterface $today = null): array
    {
        if ($error = $this->problem($ast)) {
            throw new \InvalidArgumentException($error);
        }

        $parts = [];
        $warnings = [];
        $node = $this->run($context, $ast, $today, $parts, $warnings);

        return [
            'result' => $node['value'] === null ? null : round($node['value'], 4),
            'unit' => $node['unit'],
            'message' => $node['message'],
            'warnings' => array_values(array_unique($warnings)),
            'parts' => $parts,
        ];
    }

    private function check(array $node, int $depth, int &$nodes, int &$values): ?string
    {
        if ($depth > self::MAX_DEPTH || ++$nodes > self::MAX_NODES) {
            return 'That formula is too big.';
        }

        switch ($node['type'] ?? null) {
            case 'number':
                $v = $node['value'] ?? null;

                return is_numeric($v) && is_finite((float) $v) && abs((float) $v) <= 1e12 ? null : 'Enter a number.';
            case 'value':
                if (++$values > self::MAX_VALUES) {
                    return 'At most '.self::MAX_VALUES.' values in one formula.';
                }

                return $this->catalog->validateValue($node);
            case 'group':
                return is_array($node['inner'] ?? null) ? $this->check($node['inner'], $depth + 1, $nodes, $values) : 'Empty brackets.';
            case 'op':
                if (! in_array($node['op'] ?? null, self::OPS, true)) {
                    return 'Unknown operator.';
                }
                if (! is_array($node['left'] ?? null) || ! is_array($node['right'] ?? null)) {
                    return 'An operator needs a value on both sides.';
                }

                return $this->check($node['left'], $depth + 1, $nodes, $values)
                    ?? $this->check($node['right'], $depth + 1, $nodes, $values);
        }

        return 'Unknown formula part.';
    }

    /** @return array{value:?float,unit:?string,message:?string} */
    private function run(CalculatorContext $c, array $node, ?CarbonInterface $today, array &$parts, array &$warnings): array
    {
        switch ($node['type']) {
            case 'number':
                return ['value' => (float) $node['value'], 'unit' => null, 'message' => null];

            case 'group':
                return $this->run($c, $node['inner'], $today, $parts, $warnings);

            case 'value':
                $part = $this->catalog->evaluateValue($c, $node, $today);
                $parts[] = $part;

                return ['value' => $part['value'], 'unit' => $part['unit'], 'message' => $part['value'] === null ? "{$part['label']}: ".($part['note'] ?? 'no figure.') : null];
        }

        $left = $this->run($c, $node['left'], $today, $parts, $warnings);
        $right = $this->run($c, $node['right'], $today, $parts, $warnings);
        $op = $node['op'];

        // No figure on either side, no figure out; the first reason stands.
        if ($left['value'] === null || $right['value'] === null) {
            return ['value' => null, 'unit' => null, 'message' => $left['message'] ?? $right['message']];
        }

        [$a, $b] = [$left['value'], $right['value']];
        if ($op === '/' && abs($b) < 1e-12) {
            return ['value' => null, 'unit' => null, 'message' => 'Division by zero'];
        }

        $value = match ($op) {
            '+' => $a + $b,
            '-' => $a - $b,
            '*' => $a * $b,
            '/' => $a / $b,
        };

        if (in_array($op, ['+', '-'], true) && $left['unit'] && $right['unit'] && $left['unit'] !== $right['unit']) {
            $warnings[] = 'Mixing '.$left['unit'].' and '.$right['unit'].' with '.($op === '+' ? 'plus' : 'minus').'.';
        }

        return ['value' => $value, 'unit' => self::unit($op, $left['unit'], $right['unit']), 'message' => null];
    }

    /** The unit after an operation: Rs / L is Rs/L, Rs/L x L is Rs, Rs / Rs is a plain ratio. */
    public static function unit(string $op, ?string $a, ?string $b): ?string
    {
        if ($op === '+' || $op === '-') {
            return $a ?? $b;
        }
        if ($op === '*') {
            if ($a === null || $b === null) {
                return $a ?? $b;
            }
            foreach ([[$a, $b], [$b, $a]] as [$rate, $per]) {
                if (str_ends_with($rate, '/'.$per)) {
                    return substr($rate, 0, -strlen('/'.$per));
                }
            }

            return "{$a}·{$b}";
        }

        // Division.
        if ($b === null) {
            return $a;
        }
        if ($a === $b) {
            return null;
        }
        if ($a === null) {
            return "1/{$b}";
        }
        // Rs / (Rs/L) is litres.
        if (str_starts_with($b, $a.'/')) {
            return substr($b, strlen($a) + 1);
        }

        return "{$a}/{$b}";
    }
}
