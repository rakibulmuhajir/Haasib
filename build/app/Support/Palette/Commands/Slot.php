<?php

namespace App\Support\Palette\Commands;

/** One blank in a command's sentence: what kind of value it takes. */
final class Slot
{
    /** @param list<string> $options */
    private function __construct(
        public readonly string $kind,
        public readonly ?string $entity = null,
        public readonly ?string $accountKind = null,
        public readonly array $options = [],
    ) {}

    public static function amount(): self
    {
        return new self('amount');
    }

    public static function date(): self
    {
        return new self('date');
    }

    public static function text(): self
    {
        return new self('text');
    }

    /** A record found by name through PaletteSearchSources, e.g. 'vendor'. */
    public static function search(string $entity): self
    {
        return new self('search', entity: $entity);
    }

    /** @param string $kind 'bank' | 'cash_or_bank' | 'expense' */
    public static function account(string $kind): self
    {
        return new self('account', accountKind: $kind);
    }

    /** @param list<string> $options */
    public static function choice(array $options): self
    {
        return new self('choice', options: array_values($options));
    }
}
