<?php

namespace App\Support\Palette;

/**
 * Keyed registry of palette search fields. Core registers the common entities;
 * modules register their own at boot.
 */
final class PaletteSearchSources
{
    /** @var array<string, callable(string, string): array<int, array{value: string, label: string, meta?: string, icon?: string}>> */
    private static array $sources = [];

    public static function register(string $entity, callable $search): void
    {
        self::$sources[$entity] = $search;
    }

    public static function has(string $entity): bool
    {
        return isset(self::$sources[$entity]);
    }

    /** @return array<int, array{value: string, label: string, meta?: string, icon?: string}> */
    public static function search(string $entity, string $companyId, string $query): array
    {
        $source = self::$sources[$entity] ?? null;

        return $source ? array_values($source($companyId, $query)) : [];
    }

    public static function flush(): void
    {
        self::$sources = [];
    }
}
