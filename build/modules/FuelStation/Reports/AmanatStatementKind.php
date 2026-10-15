<?php

namespace App\Modules\FuelStation\Reports;

use App\Models\Company;
use App\Modules\Accounting\Extensions\StatementKind;
use App\Modules\Accounting\Models\Customer;
use App\Modules\FuelStation\Services\AmanatStatementService;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Amanat holders get their own statement: their movements are deposits held for
 * them, not invoices and payments. A holder who also buys on credit stays under
 * Customer too. Registered with the core's StatementKinds by the FuelStation provider.
 */
class AmanatStatementKind implements StatementKind
{
    public function key(): string
    {
        return 'amanat';
    }

    public function label(): string
    {
        return 'Amanat';
    }

    public function partyLabel(): string
    {
        return 'Holder';
    }

    public function allLabel(): string
    {
        return 'All holders';
    }

    public function available(Company $company): bool
    {
        return $company->hasModule('fuel_station');
    }

    /** holder customer_id => is_credit_customer */
    private function holderIds(Company $company): Collection
    {
        return DB::table('fuel.customer_profiles')
            ->where('company_id', $company->id)
            ->where('is_amanat_holder', true)
            ->pluck('is_credit_customer', 'customer_id');
    }

    public function options(Company $company): Collection
    {
        $holderIds = $this->holderIds($company);

        return Customer::where('company_id', $company->id)
            ->where('is_active', true)
            ->orderBy('name')
            ->get(['id', 'name', 'customer_number', 'category_id'])
            ->filter(fn ($c) => $holderIds->has($c->id))
            ->values();
    }

    public function hiddenCustomerIds(Company $company): array
    {
        return $this->holderIds($company)
            ->filter(fn ($isCredit) => ! $isCredit)
            ->keys()
            ->all();
    }

    public function statement(Collection $options, ?string $id, string $from, string $to, bool $includeTrail): array
    {
        $holder = $id ? $options->firstWhere('id', $id) : null;
        $holder ??= $options->first();
        $holder = $holder ? Customer::find($holder->id) : null;
        $columns = ['money_in' => 'Deposited', 'money_out' => 'Paid out', 'balance' => 'We hold'];

        if (! $holder) {
            return [
                ['rows' => [], 'opening_balance' => 0.0, 'closing_balance' => 0.0, 'from' => $from, 'to' => $to, 'party' => null],
                $columns,
                null,
            ];
        }

        return [app(AmanatStatementService::class)->statement($holder, $from, $to, $includeTrail), $columns, $holder->id];
    }
}
