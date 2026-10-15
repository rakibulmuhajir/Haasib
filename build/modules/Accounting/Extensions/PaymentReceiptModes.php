<?php

namespace App\Modules\Accounting\Extensions;

use App\Models\Company;

/**
 * Other ways to record money received from a customer than "against invoices",
 * contributed by modules (e.g. FuelStation's amanat deposit). Keyed by mode, so
 * a provider that boots again replaces its mode instead of doubling it.
 */
class PaymentReceiptModes
{
    /** @var array<string, array{label: string, hint: ?string, available: callable, store: callable}> */
    private static array $modes = [];

    /**
     * @param  callable(Company, mixed): bool  $available
     * @param  callable(\App\Modules\Accounting\Http\Requests\StorePaymentRequest, Company, array): \Illuminate\Http\RedirectResponse  $store
     */
    public static function register(string $key, string $label, callable $available, callable $store, ?string $hint = null): void
    {
        self::$modes[$key] = ['label' => $label, 'hint' => $hint, 'available' => $available, 'store' => $store];
    }

    public static function flush(): void
    {
        self::$modes = [];
    }

    /** @return array<int, array{key: string, label: string, hint: ?string}> */
    public function availableFor(Company $company, $user): array
    {
        $out = [];
        foreach (self::$modes as $key => $mode) {
            if (($mode['available'])($company, $user)) {
                $out[] = ['key' => $key, 'label' => $mode['label'], 'hint' => $mode['hint']];
            }
        }

        return $out;
    }

    public function handler(Company $company, $user, string $key): ?callable
    {
        $mode = self::$modes[$key] ?? null;

        return $mode && ($mode['available'])($company, $user) ? $mode['store'] : null;
    }
}
