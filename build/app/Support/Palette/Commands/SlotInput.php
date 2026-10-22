<?php

namespace App\Support\Palette\Commands;

/** What the browser sent for each slot: the text typed and, once picked from choices, the chosen id. */
final class SlotInput
{
    /** @param array<string, array{text?: ?string, value?: ?string}> $slots */
    public function __construct(private readonly array $slots = []) {}

    public function text(string $slot): ?string
    {
        return $this->read($slot, 'text');
    }

    public function value(string $slot): ?string
    {
        return $this->read($slot, 'value');
    }

    private function read(string $slot, string $field): ?string
    {
        $raw = $this->slots[$slot][$field] ?? null;
        $clean = is_scalar($raw) ? trim((string) $raw) : '';

        return $clean === '' ? null : $clean;
    }
}
