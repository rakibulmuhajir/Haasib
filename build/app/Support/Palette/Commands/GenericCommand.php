<?php

namespace App\Support\Palette\Commands;

use App\Models\Company;
use App\Support\Palette\PaletteSearchSources;

/**
 * A catalog entry (a CommandBus action no declared command targets) typed as a sentence:
 * the description, then one blank per field. Fills the palette until every action has a
 * hand-written command.
 */
final class GenericCommand extends PaletteCommand
{
    /** @param array<string, mixed> $entry one PaletteCatalog::for() entry */
    public function __construct(private readonly array $entry) {}

    public function name(): string
    {
        return $this->entry['key'];
    }

    public function label(): string
    {
        return $this->entry['description'];
    }

    public function sentence(): string
    {
        $parts = [$this->entry['description']];
        foreach ($this->entry['args'] as $f) {
            $parts[] = '{'.$f['name'].'}';
        }
        foreach ($this->entry['flags'] as $f) {
            $parts[] = '{'.$f['name'].'?}';
        }

        return implode(' ', $parts);
    }

    public function slots(): array
    {
        $slots = [];
        foreach ($this->fields() as $f) {
            $slots[$f['name']] = match ($f['inputType'] ?? 'text') {
                'search' => Slot::search($f['searchEntity'] ?? $f['name']),
                'select' => Slot::choice($f['enum'] ?? []),
                'number' => Slot::amount(),
                'date' => Slot::date(),
                default => Slot::text(),
            };
        }

        return $slots;
    }

    public function target(): string
    {
        return $this->entry['key'];
    }

    public function formUrl(): ?string
    {
        return $this->entry['form'] ? $this->entry['form_url'] : null;
    }

    public function query(): bool
    {
        return (bool) $this->entry['query'];
    }

    public function toCatalog(Company $company): array
    {
        return array_merge(parent::toCatalog($company), ['generic' => true]);
    }

    public function resolve(SlotInput $in, ResolveContext $ctx): Resolution
    {
        $states = [];
        $params = [];
        $pending = false;

        foreach ($this->fields() as $f) {
            $name = $f['name'];
            $text = $in->text($name);
            $picked = $in->value($name);

            if ($text === null && $picked === null) {
                if ($f['required']) {
                    $states[$name] = ['state' => 'missing'];
                    $pending = true;
                }

                continue;
            }

            [$state, $value] = $this->fill($f, $text, $picked, $ctx);
            $states[$name] = $state;
            if ($state['state'] === 'resolved') {
                $params[$name] = $value;
            } else {
                $pending = true;
            }
        }

        if ($pending) {
            return Resolution::pending($states);
        }

        return Resolution::ready(
            $this->target(),
            $params,
            ['what' => $this->label(), 'currency' => $ctx->company->base_currency],
            $this->label(),
        );
    }

    /** @return list<array<string, mixed>> */
    private function fields(): array
    {
        return array_merge($this->entry['args'], $this->entry['flags']);
    }

    /** @return array{0: array<string, mixed>, 1: mixed} slot state and the param value */
    private function fill(array $f, ?string $text, ?string $picked, ResolveContext $ctx): array
    {
        $type = $f['inputType'] ?? 'text';

        if ($type === 'search') {
            if ($picked !== null) {
                return [['state' => 'resolved', 'value' => $picked, 'label' => $text ?? $picked], $picked];
            }

            return $this->search($f['searchEntity'] ?? $f['name'], (string) $text, $ctx);
        }

        if ($type === 'select') {
            $wanted = (string) ($picked ?? $text);
            foreach ($f['enum'] ?? [] as $option) {
                if (strcasecmp((string) $option, $wanted) === 0) {
                    return [['state' => 'resolved', 'value' => (string) $option, 'label' => (string) $option], (string) $option];
                }
            }

            return [['state' => 'not_found', 'message' => 'Not found'], null];
        }

        if ($type === 'number') {
            $amount = AmountParser::parse((string) $text);

            return $amount === null
                ? [['state' => 'not_found', 'message' => 'Amount needed'], null]
                : [['state' => 'resolved', 'value' => $amount, 'label' => (string) $text], $amount];
        }

        if ($type === 'date') {
            $date = DateParser::resolve((string) $text, $ctx->today);

            return $date['ok']
                ? [['state' => 'resolved', 'value' => $date['date'], 'label' => $date['date']], $date['date']]
                : [['state' => 'not_found', 'message' => $date['message']], null];
        }

        return [['state' => 'resolved', 'value' => $text, 'label' => $text], $text];
    }

    /** @return array{0: array<string, mixed>, 1: mixed} */
    private function search(string $entity, string $text, ResolveContext $ctx): array
    {
        $hits = PaletteSearchSources::search($entity, $ctx->company->id, $text);
        if ($hits === []) {
            return [['state' => 'not_found', 'message' => 'Not found'], null];
        }

        $exact = array_values(array_filter($hits, fn ($h) => strcasecmp($h['label'], $text) === 0));
        $hit = count($hits) === 1 ? $hits[0] : (count($exact) === 1 ? $exact[0] : null);
        if ($hit !== null) {
            return [['state' => 'resolved', 'value' => $hit['value'], 'label' => $hit['label']], $hit['value']];
        }

        return [[
            'state' => 'choices',
            'prompt' => 'Which one?',
            'choices' => array_map(fn ($h) => array_filter(
                ['value' => $h['value'], 'label' => $h['label'], 'meta' => $h['meta'] ?? null],
                fn ($v) => $v !== null,
            ), $hits),
        ], null];
    }
}
