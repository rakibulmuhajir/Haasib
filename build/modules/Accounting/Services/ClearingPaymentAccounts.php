<?php

namespace App\Modules\Accounting\Services;

/**
 * Which clearing accounts a supplier payment may be made from.
 *
 * A supplier payment normally leaves a cash or bank account. A module can name clearing accounts
 * that settle straight to a supplier - the fuel module's card channels set to "Settles to:
 * Supplier", where the oil company deducts card sales from what the station owes. The core never
 * imports the module: the module registers a resolver from its service provider, the same way it
 * registers day locks with DocumentDateLock.
 */
class ClearingPaymentAccounts
{
    /** @var array<int, callable(string, string): bool> */
    private static array $resolvers = [];

    /** Register a resolver: given (companyId, accountId), true when that account may pay a supplier. */
    public static function extend(callable $resolver): void
    {
        self::$resolvers[] = $resolver;
    }

    public function allows(string $companyId, string $accountId): bool
    {
        foreach (self::$resolvers as $resolver) {
            if ($resolver($companyId, $accountId)) {
                return true;
            }
        }

        return false;
    }
}
