<?php

namespace App\Modules\FuelStation\Services\Calculator;

use App\Models\Company;
use App\Modules\Accounting\Models\Account;
use App\Modules\Accounting\Models\Customer;
use App\Modules\FuelStation\Models\CalculatorFormula;
use App\Modules\FuelStation\Services\Calculator\Metrics\AccountMetric;
use Illuminate\Support\Facades\DB;

/** What the Calculator page needs: running a formula, the things a value can be read for, the saved list. */
class CalculatorService
{
    public function __construct(
        private readonly MetricCatalog $catalog,
        private readonly FormulaEvaluator $evaluator,
    ) {}

    public function run(Company $company, array $formula): array
    {
        return $this->evaluator->evaluate(new CalculatorContext($company->id, (string) $company->slug), $formula);
    }

    /** @return array<string,mixed> */
    public function options(string $companyId): array
    {
        $accounts = fn (array $types) => Account::where('company_id', $companyId)->whereNull('deleted_at')->where('is_active', true)
            ->whereIn('type', $types)->orderBy('code')->get(['id', 'code', 'name'])
            ->map(fn ($a) => ['id' => $a->id, 'name' => trim($a->code.' '.$a->name)])->all();

        return [
            'products' => DB::table('inv.items')->where('company_id', $companyId)->whereNull('deleted_at')
                ->orderBy('name')->get(['id', 'name', 'fuel_category'])
                ->map(fn ($i) => ['id' => $i->id, 'name' => $i->name, 'is_fuel' => (bool) $i->fuel_category])->all(),
            'product_categories' => DB::table('inv.item_categories')->where('company_id', $companyId)->whereNull('deleted_at')
                ->orderBy('name')->get(['id', 'name'])->map(fn ($c) => ['id' => $c->id, 'name' => $c->name])->all(),
            'customer_categories' => DB::table('acct.customer_categories')->where('company_id', $companyId)
                ->orderBy('name')->get(['id', 'name'])->map(fn ($c) => ['id' => $c->id, 'name' => $c->name])->all(),
            'expense_accounts' => $accounts(AccountMetric::EXPENSE_TYPES),
            'income_accounts' => $accounts(AccountMetric::INCOME_TYPES),
            'customers' => Customer::where('company_id', $companyId)->where('is_active', true)->orderBy('name')->get(['id', 'name'])
                ->map(fn ($c) => ['id' => $c->id, 'name' => $c->name])->all(),
            'channels' => collect(\App\Modules\FuelStation\Models\StationSettings::where('company_id', $companyId)->first()?->payment_channels ?? [])
                ->filter(fn ($ch) => ($ch['type'] ?? '') !== 'cash' && ! empty($ch['code']))
                ->map(fn ($ch) => ['id' => (string) $ch['code'], 'name' => (string) ($ch['label'] ?? $ch['code'])])->values()->all(),
        ];
    }

    public function metrics(): array
    {
        return $this->catalog->describe();
    }

    /** The saved formulas this person can open: their own, and everyone's shared ones. */
    public function saved(string $companyId, string $userId): array
    {
        return CalculatorFormula::with('user:id,name')
            ->where('company_id', $companyId)
            ->where(fn ($q) => $q->where('user_id', $userId)->orWhere('is_shared', true))
            ->orderBy('name')
            ->get()
            ->map(fn (CalculatorFormula $f) => [
                'id' => $f->id,
                'name' => $f->name,
                'formula' => $f->formula,
                'is_shared' => $f->is_shared,
                'mine' => $f->user_id === $userId,
                'owner' => $f->user?->name,
            ])->all();
    }

    /** Average sale rate of diesel this month: Sales / Litres sold. Falls back to all fuels. */
    public function example(string $companyId): array
    {
        $diesel = DB::table('inv.items')->where('company_id', $companyId)->whereNull('deleted_at')
            ->where(fn ($q) => $q->where('fuel_category', 'ilike', 'diesel')->orWhere('name', 'ilike', 'diesel'))
            ->orderByRaw('fuel_category is null')->value('id');
        $collection = $diesel ? ['type' => 'product', 'id' => $diesel] : ['type' => 'all_fuels'];
        $when = ['preset' => 'this_month'];

        return [
            'type' => 'op', 'op' => '/',
            'left' => ['type' => 'value', 'metric' => 'sales', 'collection' => $collection, 'when' => $when],
            'right' => ['type' => 'value', 'metric' => 'litres_sold', 'collection' => $collection, 'when' => $when],
        ];
    }
}
