<?php

namespace App\Modules\Accounting\Actions\OpeningBalance;

/**
 * Turns opening_balance.view's response back into an opening_balance.save payload.
 *
 * ViewAction returns display names and derived figures alongside the raw rows (customer_name,
 * invoice_id, paid_amount, ...) that SaveAction's rules() would reject. Both SetAccountAction
 * and SetPartyAction need to read the current set, replace one line, and repost the lot — this
 * is the read-merge-write they share, extracted so a third caller does not have to copy it a
 * second time.
 */
final class OpeningSet
{
    /**
     * @param  array<string, mixed>  $view  the array opening_balance.view returned
     * @return array<string, mixed>  a payload ready for opening_balance.save, carrying every
     *                                row from $view untouched and the view's own as_of_date
     */
    public static function payloadFromView(array $view): array
    {
        $rows = $view['rows'];

        return [
            'as_of_date' => $view['as_of_date'] ?? now()->toDateString(),
            'cash' => ['amount' => (float) ($rows['cash']['amount'] ?? 0)],
            'banks' => collect($rows['banks'] ?? [])
                ->map(fn ($b) => ['account_id' => $b['account_id'], 'amount' => (float) $b['amount']])
                ->values()
                ->all(),
            'credit_customers' => self::carry($rows['credit_customers'] ?? [], 'customer_id'),
            'employees' => self::carry($rows['employees'] ?? [], 'employee_id'),
            'salaries_owed' => self::carry($rows['salaries_owed'] ?? [], 'employee_id'),
            'amanat' => self::carry($rows['amanat'] ?? [], 'customer_id'),
            'suppliers' => self::carry($rows['suppliers'] ?? [], 'vendor_id'),
            'partners' => self::carry($rows['partners'] ?? [], 'partner_id'),
        ];
    }

    /**
     * Carried through untouched, mapped down to the keys SaveAction accepts: the view returns
     * display names and derived figures alongside them.
     *
     * @param  array<int, array<string, mixed>>  $rows
     * @return array<int, array<string, mixed>>
     */
    public static function carry(array $rows, string $key): array
    {
        return collect($rows)
            ->map(fn ($r) => [$key => $r[$key], 'amount' => (float) $r['amount']])
            ->values()
            ->all();
    }
}
