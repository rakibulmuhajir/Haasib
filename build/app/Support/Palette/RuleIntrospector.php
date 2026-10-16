<?php

namespace App\Support\Palette;

/**
 * Reads a Laravel rules() array and says what a palette field for each key looks like.
 * Container-free on purpose: it only inspects strings and rule objects.
 */
final class RuleIntrospector
{
    /** Entities a `{x}_id` field can search. */
    private const SEARCHABLE = ['customer', 'vendor', 'invoice', 'bill', 'item', 'employee', 'warehouse', 'account', 'user'];

    /**
     * @return array{fields: list<array{name: string, required: bool, inputType: string, enum?: list<string>, searchEntity?: string, hint?: string}>, complex: bool}
     */
    public function describe(array $rules): array
    {
        $fields = [];
        $complex = false;

        foreach ($rules as $name => $fieldRules) {
            $name = (string) $name;
            if (str_contains($name, '.')) {
                continue;
            }

            $tokens = $this->tokens($fieldRules);
            $required = $this->isRequired($tokens);

            if (in_array('array', $tokens, true)) {
                if ($required) {
                    $complex = true;
                }

                continue;
            }

            $fields[] = ['name' => $name, 'required' => $required] + $this->type($name, $tokens);
        }

        return ['fields' => $fields, 'complex' => $complex];
    }

    /** @return list<string> */
    private function tokens(mixed $fieldRules): array
    {
        $items = is_string($fieldRules) ? explode('|', $fieldRules) : (array) $fieldRules;
        $tokens = [];

        foreach ($items as $item) {
            if (is_string($item)) {
                $tokens[] = trim($item);
            } elseif ($item instanceof \Stringable) {
                try {
                    $tokens[] = trim((string) $item);
                } catch (\Throwable) {
                    // A rule object that cannot describe itself adds nothing.
                }
            }
        }

        return array_values(array_filter($tokens, fn ($t) => $t !== ''));
    }

    private function isRequired(array $tokens): bool
    {
        foreach ($tokens as $t) {
            if ($t === 'required' || str_starts_with($t, 'required_with')) {
                return true;
            }
        }

        return false;
    }

    private function type(string $name, array $tokens): array
    {
        $has = fn (string $needle) => in_array($needle, $tokens, true);
        $prefixed = function (string $prefix) use ($tokens) {
            foreach ($tokens as $t) {
                if (str_starts_with($t, $prefix)) {
                    return $t;
                }
            }

            return null;
        };

        if ($in = $prefixed('in:')) {
            $values = array_map(fn ($v) => trim((string) $v, '"'), str_getcsv(substr($in, 3), ',', '"', ''));

            return ['inputType' => 'select', 'enum' => array_values($values)];
        }

        if ($name === 'currency' || str_ends_with($name, '_currency')) {
            return ['inputType' => 'lookup', 'searchEntity' => 'currency'];
        }

        if ($has('boolean') || $has('bool')) {
            return ['inputType' => 'select', 'enum' => ['true', 'false']];
        }

        if ($has('numeric') || $has('integer') || $has('decimal') || $prefixed('decimal:')) {
            return ['inputType' => 'number'];
        }

        if ($has('date') || $prefixed('date_format:')) {
            return ['inputType' => 'date'];
        }

        if ($has('email')) {
            return ['inputType' => 'email'];
        }

        if (str_ends_with($name, '_id') && ($has('uuid') || $prefixed('exists:'))) {
            $entity = $this->searchEntity(substr($name, 0, -3));
            if ($entity !== null) {
                return ['inputType' => 'search', 'searchEntity' => $entity];
            }
        }

        return ['inputType' => 'text'];
    }

    private function searchEntity(string $stem): ?string
    {
        if (str_ends_with($stem, '_account')) {
            return 'account';
        }
        if (in_array($stem, self::SEARCHABLE, true)) {
            return $stem;
        }
        $last = str_contains($stem, '_') ? substr($stem, strrpos($stem, '_') + 1) : $stem;

        return in_array($last, self::SEARCHABLE, true) ? $last : null;
    }
}
