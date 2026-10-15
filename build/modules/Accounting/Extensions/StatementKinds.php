<?php

namespace App\Modules\Accounting\Extensions;

use App\Models\Company;

/**
 * Statement kinds and "go to another report" tabs contributed by modules.
 * Keyed, so a provider that boots again replaces its entry instead of doubling it.
 */
class StatementKinds
{
    /** @var array<string, StatementKind> */
    private static array $kinds = [];

    /** @var array<string, array{label: string, available: callable, href: callable}> */
    private static array $links = [];

    public static function register(StatementKind $kind): void
    {
        self::$kinds[$kind->key()] = $kind;
    }

    /**
     * @param  callable(Company): bool  $available
     * @param  callable(Company, string, string): string  $href  fn (Company, $from, $to)
     */
    public static function registerLink(string $key, string $label, callable $available, callable $href): void
    {
        self::$links[$key] = ['label' => $label, 'available' => $available, 'href' => $href];
    }

    public static function flush(): void
    {
        self::$kinds = [];
        self::$links = [];
    }

    /** @return array<string, StatementKind> */
    public function availableFor(Company $company): array
    {
        return array_filter(self::$kinds, fn (StatementKind $kind) => $kind->available($company));
    }

    /** @return array<int, array{key: string, label: string, href: string}> */
    public function links(Company $company, string $from = '', string $to = ''): array
    {
        $out = [];
        foreach (self::$links as $key => $link) {
            if (($link['available'])($company)) {
                $out[] = ['key' => $key, 'label' => $link['label'], 'href' => ($link['href'])($company, $from, $to)];
            }
        }

        return $out;
    }
}
