<?php

namespace App\Modules\Accounting\Actions\OpeningBalance;

use App\Constants\Permissions;
use App\Contracts\PaletteAction;
use App\Facades\CompanyContext;
use App\Modules\Accounting\Models\Customer;
use App\Modules\Accounting\Models\Vendor;
use App\Services\CommandBus;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * Set the opening balance of ONE customer or vendor, from wherever the user happens to be —
 * today, Quick Add.
 *
 * The mirror of SetAccountAction, for the party side of the opening set instead of the money
 * side: it reads the current set via opening_balance.view, replaces the one line for this
 * party in the named section, and hands the whole thing to opening_balance.save, which stays
 * the only thing that ever posts an opening balance. Every guard SaveAction carries therefore
 * applies here too, for free.
 *
 * "section" is one of the three party-shaped rows a customer or vendor can appear in:
 * credit_customers (a customer owes the company), amanat (the company holds money in trust for
 * a customer), suppliers (the company owes a vendor). Employees and partners have no Quick Add
 * entry point and are not reachable through this action.
 */
class SetPartyAction implements PaletteAction
{
    private const SECTIONS = [
        'credit_customers' => ['model' => Customer::class, 'key' => 'customer_id'],
        'amanat' => ['model' => Customer::class, 'key' => 'customer_id'],
        'suppliers' => ['model' => Vendor::class, 'key' => 'vendor_id'],
    ];

    public function rules(): array
    {
        return [
            'section' => ['required', Rule::in(array_keys(self::SECTIONS))],
            'party_id' => ['required', 'uuid'],
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

        $section = $params['section'];
        $config = self::SECTIONS[$section];
        $modelClass = $config['model'];
        $key = $config['key'];

        $party = $modelClass::where('company_id', $company->id)->find($params['party_id']);
        if (! $party) {
            throw ValidationException::withMessages([
                'party_id' => 'Not a customer or vendor of this company.',
            ]);
        }

        $bus = app(CommandBus::class);

        // Read under the caller's own authority to build the merge. The permission that
        // matters was already checked against this action, and it is the stricter of the
        // two, so a caller allowed here is allowed to see what it is about to rewrite.
        $view = $bus->dispatch('opening_balance.view', [], $user, true);

        if (! empty($view['locked_at'])) {
            throw ValidationException::withMessages([
                'amount' => 'Opening balances are locked.',
            ]);
        }

        $amount = round((float) $params['amount'], 2);

        $asOfDate = $params['as_of_date'] ?? $view['as_of_date'] ?? null;
        if (! $asOfDate) {
            throw ValidationException::withMessages([
                'as_of_date' => 'Enter the date these balances are as of.',
            ]);
        }

        $payload = OpeningSet::payloadFromView($view);
        $payload['as_of_date'] = $asOfDate;
        $payload[$section] = collect($payload[$section])
            ->reject(fn ($row) => $row[$key] === $party->id)
            ->values()
            ->all();

        if ($amount > 0) {
            $payload[$section][] = [$key => $party->id, 'amount' => $amount];
        }

        $result = $bus->dispatch('opening_balance.save', $payload, $user);

        return [
            'message' => 'Opening balance set for '.$party->name,
            'data' => $result['data'] ?? [],
        ];
    }
}
