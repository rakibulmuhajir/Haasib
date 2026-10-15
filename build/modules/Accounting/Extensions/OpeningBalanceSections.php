<?php

namespace App\Modules\Accounting\Extensions;

use App\Models\Company;

/**
 * Opening-balance sections contributed by modules. Keyed, so a provider that boots
 * again replaces its entry instead of doubling it.
 */
class OpeningBalanceSections
{
    /** @var array<string, OpeningBalanceSection> */
    private static array $sections = [];

    public static function register(OpeningBalanceSection $section): void
    {
        self::$sections[$section->key()] = $section;
    }

    public static function flush(): void
    {
        self::$sections = [];
    }

    /** @return array<string, OpeningBalanceSection> */
    public function availableFor(Company $company): array
    {
        return array_filter(self::$sections, fn (OpeningBalanceSection $s) => $s->available($company));
    }

    /**
     * The section that holds money a customer paid in advance (a customer liability),
     * when this company has one. Quick Add's "paid in advance" field posts to it.
     */
    public function customerAdvanceFor(Company $company): ?OpeningBalanceSection
    {
        foreach ($this->availableFor($company) as $section) {
            if ($section->party() === 'customer' && $section->side() === 'liability') {
                return $section;
            }
        }

        return null;
    }

    /**
     * Every registered section, available or not: re-entering must undo what a section
     * posted even if the company has since lost the module.
     *
     * @return array<string, OpeningBalanceSection>
     */
    public function all(): array
    {
        return self::$sections;
    }
}
