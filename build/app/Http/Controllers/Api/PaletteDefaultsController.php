<?php

namespace App\Http\Controllers\Api;

use App\Facades\CompanyContext;
use App\Http\Controllers\Controller;
use App\Http\Requests\PaletteOpenBillsRequest;
use App\Models\Company;
use App\Modules\Accounting\Models\Account;
use App\Modules\Accounting\Models\Bill;
use App\Support\CompanyClock;
use Illuminate\Http\JsonResponse;

/**
 * What the quick-entry grammar needs to fill in the blanks of a one-line transaction:
 * the company date, its default cash and bank accounts, and the small account lists
 * a typed name is matched against.
 */
class PaletteDefaultsController extends Controller
{
    public function __invoke(): JsonResponse
    {
        $company = CompanyContext::getCompany();
        if (! $company) {
            return response()->json(['message' => 'Choose a company first'], 400);
        }

        return response()->json(self::defaultsFor($company))->header('Cache-Control', 'no-store');
    }

    /** The company date, currency, default accounts and the account lists a typed name is matched against. */
    public static function defaultsFor(Company $company): array
    {
        $accounts = fn (string $column, array $values) => Account::where('company_id', $company->id)
            ->where('is_active', true)->whereNull('deleted_at')->whereIn($column, $values)
            ->orderBy('code')->get(['id', 'code', 'name']);

        $banks = $accounts('subtype', ['bank']);
        $cash = $accounts('subtype', ['cash']);
        $expense = $accounts('type', ['expense']);

        // The sales page's own notion of the default bank, when it is a bank account.
        $defaultBank = $banks->firstWhere('id', $company->bank_account_id) ?? $banks->first();

        // Where one-line entries put money when the line names no account (Settings, Quick entry).
        // An account that has since been closed or changed type is ignored.
        $chosen = $company->quickEntryDefaults();
        $usable = $cash->concat($banks);
        $receive = $usable->firstWhere('id', $chosen['receive_account_id']);
        $pay = $usable->firstWhere('id', $chosen['pay_account_id']);

        $charges = $expense->first(fn ($a) => (bool) preg_match('/bank\s+(charge|fee|service)/i', $a->name));

        return [
            'today' => app(CompanyClock::class)->today(),
            'currency' => $company->base_currency,
            'default_bank_id' => $defaultBank?->id,
            'default_cash_id' => $cash->first()?->id,
            'receive_account_id' => $receive?->id,
            'pay_account_id' => $pay?->id,
            'bank_charges_account_id' => $charges?->id,
            'banks' => $banks->values()->toArray(),
            'cash' => $cash->values()->toArray(),
            'expense' => $expense->values()->toArray(),
        ];
    }

    /** A vendor's open bills in the company currency, oldest first, so a payment can settle them in order. */
    public function openBills(PaletteOpenBillsRequest $request): JsonResponse
    {
        $company = CompanyContext::getCompany();

        $bills = Bill::where('company_id', $company->id)
            ->where('vendor_id', $request->validated('vendor_id'))
            ->whereNotIn('status', ['paid', 'void', 'cancelled', 'draft'])
            ->where('balance', '>', 0)
            ->where('currency', $company->base_currency)
            ->orderBy('bill_date')->orderBy('bill_number')
            ->get(['id', 'bill_number', 'balance', 'currency'])
            ->map(fn ($b) => [
                'id' => $b->id,
                'bill_number' => $b->bill_number,
                'balance' => round((float) $b->balance, 2),
                'currency' => $b->currency,
            ]);

        return response()->json(['bills' => $bills->values()])->header('Cache-Control', 'no-store');
    }
}
