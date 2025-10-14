<?php

namespace App\Support\Filtering;

use Illuminate\Database\Eloquent\Builder;

class FilterBuilder
{
    /**
     * Apply normalized filter rules to an Eloquent query.
     *
     * @param  array{logic?:string,rules:array<int,array{field:string,operator:string,value:mixed}>}  $filters
     * @param  array  $fieldMap  Example: ['amount' => 'amount', 'created_at' => 'created_at', 'customer_name' => ['relation' => 'invoices.customer', 'column' => 'name']]
     */
    public function apply(Builder $query, array $filters, array $fieldMap): Builder
    {
        $rules = $filters['rules'] ?? [];
        $logic = strtolower($filters['logic'] ?? 'and') === 'or' ? 'or' : 'and';

        foreach ($rules as $rule) {
            $field = $rule['field'] ?? null;
            $op = strtolower($rule['operator'] ?? '');
            $value = $rule['value'] ?? null;

            if (! $field || ! isset($fieldMap[$field])) {
                continue; // unknown field
            }

            $mapping = $fieldMap[$field];

            $apply = function (Builder $q) use ($mapping, $op, $value) {
                if (is_array($mapping) && isset($mapping['relation'], $mapping['column'])) {
                    // Relation field: whereHas(relation, column ...)
                    $relation = $mapping['relation'];
                    $column = $mapping['column'];

                    $q->whereHas($relation, function (Builder $rq) use ($column, $op, $value) {
                        $this->applyWhere($rq, $column, $op, $value);
                    });
                } else {
                    // Direct column
                    $column = $mapping;
                    $this->applyWhere($q, $column, $op, $value);
                }
            };

            if ($logic === 'or') {
                $query->orWhere(fn ($q) => $apply($q));
            } else {
                $query->where(fn ($q) => $apply($q));
            }
        }

        return $query;
    }

    private function applyWhere(Builder $q, string $column, string $op, $value): void
    {
        switch ($op) {
            case 'eq':
            case 'equals':
                $q->where($column, '=', $value);
                break;
            case 'lt':
            case 'lessthan':
                $q->where($column, '<', $this->cast($value));
                break;
            case 'lte':
            case 'lessthanorequal':
                $q->where($column, '<=', $this->cast($value));
                break;
            case 'gt':
            case 'greaterthan':
                $q->where($column, '>', $this->cast($value));
                break;
            case 'gte':
            case 'greaterthanorequal':
                $q->where($column, '>=', $this->cast($value));
                break;
            case 'between':
                if (is_array($value) && count($value) === 2) {
                    [$min, $max] = $value;
                    if ($min !== null && $min !== '') {
                        $q->where($column, '>=', $this->cast($min));
                    }
                    if ($max !== null && $max !== '') {
                        $q->where($column, '<=', $this->cast($max));
                    }
                }
                break;
            case 'contains':
                $q->where($column, 'ilike', '%'.$value.'%');
                break;
            case 'starts_with':
                $q->where($column, 'ilike', $value.'%');
                break;
            case 'in':
                $vals = is_array($value) ? $value : [$value];
                $q->whereIn($column, $vals);
                break;
            case 'on':
                $q->whereDate($column, '=', $value);
                break;
            case 'before':
                $q->whereDate($column, '<=', $value);
                break;
            case 'after':
                $q->whereDate($column, '>=', $value);
                break;
            default:
                // no-op for unknown operators
                break;
        }
    }

    private function cast($value)
    {
        if (is_numeric($value)) {
            return $value + 0; // cast to int/float
        }

        return $value;
    }
}
