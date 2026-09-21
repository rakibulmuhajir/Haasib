<?php

namespace App\Modules\Accounting\Actions\OpeningBalance;

use App\Constants\Permissions;
use App\Contracts\PaletteAction;
use App\Facades\CompanyContext;
use App\Modules\Accounting\Models\Account;
use App\Services\CommandBus;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * Set the opening balance of ONE money account, from wherever the user happens to be.
 *
 * The bank account page needs to set a single figure; opening_balance.save takes the whole
 * opening set and retires the previous generation to repost it. The gap between those two
 * shapes is a read-merge-write, and it belongs here rather than in a controller: put it in
 * the page and the next screen that needs it copies the code, which is the duplication this
 * whole arrangement exists to avoid.
 *
 * This action posts nothing itself. It reads the current set, replaces one line, and hands
 * the result to SaveAction, which stays the only thing that writes an opening balance. Every
 * guard SaveAction carries — the lock check, the advisory lock, the date bound — therefore
 * applies here too, for free. That is the point: a caller cannot get around them except by
 * writing to the table directly, which is exactly the bug this replaces.
 *
 * Re-saving reverses and reposts the whole generation. That is inherent to how opening
 * balances work and is what the Opening Balances page already does on every save — it seeds
 * its form from ViewAction and posts the lot back.
 */
class SetAccountAction implements PaletteAction
{
    public function rules(): array
    {
        return [
            'gl_account_id' => ['required', 'uuid', Rule::exists(Account::class, 'id')],
            'amount' => ['required', 'numeric', 'min:0'],
            'as_of_date' => ['nullable', 'date'],
        ];
    }

    public function permission(): ?string
    {
        return Permissions::OPENING_BALANCE_MANAGE;
    }

    public function handle(array $params): array
    {
        $company = CompanyContext::requireCompany();
        $user = Auth::user();

        $account = Account::where('company_id', $company->id)
            ->whereKey($params['gl_account_id'])
            ->first();

        if (! $account || ! in_array($account->subtype, ['bank', 'cash'], true)) {
            throw ValidationException::withMessages([
                'gl_account_id' => 'Not a bank or cash account of this company.',
            ]);
        }

        $bus = app(CommandBus::class);

        // Read under the caller's own authority to build the merge. The permission that
        // matters was already checked against this action, and it is the stricter of the
        // two, so a caller allowed here is allowed to see what it is about to rewrite.
        $view = $bus->dispatch('opening_balance.view', [], $user, true);
        $rows = $view['rows'];

        if (! empty($view['locked_at'])) {
            throw ValidationException::withMessages([
                'amount' => 'Opening balances are locked.',
            ]);
        }

        $amount = round((float) $params['amount'], 2);

        $payload = [
            // An existing set keeps its own date unless the caller names one. Changing one
            // account's figure must not silently re-date the entire opening position.
            'as_of_date' => $params['as_of_date']
                ?? $view['as_of_date']
                ?? now()->toDateString(),
            'cash' => ['amount' => (float) ($rows['cash']['amount'] ?? 0)],
            'banks' => collect($rows['banks'] ?? [])
                ->reject(fn ($b) => $b['account_id'] === $account->id)
                ->map(fn ($b) => ['account_id' => $b['account_id'], 'amount' => (float) $b['amount']])
                ->values()
                ->all(),
            // Carried through untouched, mapped down to the keys SaveAction accepts: the
            // view returns display names and derived figures alongside them.
            'credit_customers' => self::carry($rows['credit_customers'] ?? [], 'customer_id'),
            'employees' => self::carry($rows['employees'] ?? [], 'employee_id'),
            'amanat' => self::carry($rows['amanat'] ?? [], 'customer_id'),
            'suppliers' => self::carry($rows['suppliers'] ?? [], 'vendor_id'),
            'partners' => self::carry($rows['partners'] ?? [], 'partner_id'),
        ];

        if ($account->subtype === 'cash') {
            $payload['cash'] = ['amount' => $amount];
        } elseif ($amount > 0) {
            $payload['banks'][] = ['account_id' => $account->id, 'amount' => $amount];
        }

        $result = $bus->dispatch('opening_balance.save', $payload, $user);

        return [
            'message' => 'Opening balance set for '.$account->name,
            'data' => $result['data'] ?? [],
        ];
    }

    /**
     * @param  array<int, array<string, mixed>>  $rows
     * @return array<int, array<string, mixed>>
     */
    private static function carry(array $rows, string $key): array
    {
        return collect($rows)
            ->map(fn ($r) => [$key => $r[$key], 'amount' => (float) $r['amount']])
            ->values()
            ->all();
    }
}
