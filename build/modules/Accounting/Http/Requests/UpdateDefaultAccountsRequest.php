<?php

namespace App\Modules\Accounting\Http\Requests;

use App\Constants\Permissions;
use App\Http\Requests\BaseFormRequest;
use App\Services\CompanyContextService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\Rule;

class UpdateDefaultAccountsRequest extends BaseFormRequest
{
    public function authorize(): bool
    {
        return $this->hasCompanyPermission(Permissions::ACCOUNT_UPDATE)
            && $this->validateRlsContext();
    }

    public function rules(): array
    {
        $companyId = app(CompanyContextService::class)->getCompanyId();

        $accountExists = fn () => Rule::exists('acct.accounts', 'id')->where('company_id', $companyId)->whereNull('deleted_at');
        $requireSubtype = function (array|string $subtype) use ($companyId) {
            $subtypes = (array) $subtype;
            return function (string $attribute, mixed $value, \Closure $fail) use ($companyId, $subtypes) {
                $ok = DB::table('acct.accounts')
                    ->where('company_id', $companyId)
                    ->where('id', $value)
                    ->whereNull('deleted_at')
                    ->whereIn('subtype', $subtypes)
                    ->exists();
                if (! $ok) {
                    $fail("Selected account is not valid for {$attribute}.");
                }
            };
        };
        $requireType = function (array|string $type) use ($companyId) {
            $types = (array) $type;
            return function (string $attribute, mixed $value, \Closure $fail) use ($companyId, $types) {
                $ok = DB::table('acct.accounts')
                    ->where('company_id', $companyId)
                    ->where('id', $value)
                    ->whereNull('deleted_at')
                    ->whereIn('type', $types)
                    ->exists();
                if (! $ok) {
                    $fail("Selected account is not valid for {$attribute}.");
                }
            };
        };

        $hasTransitLoss = Schema::connection('pgsql')->hasColumn('auth.companies', 'transit_loss_account_id');
        $hasTransitGain = Schema::connection('pgsql')->hasColumn('auth.companies', 'transit_gain_account_id');
        $transitRules = [
            'transit_loss_account_id' => ['required', 'uuid', $accountExists(), $requireType(['expense', 'cogs', 'other_expense'])],
            'transit_gain_account_id' => ['required', 'uuid', $accountExists(), $requireType('other_income')],
        ];
        if (! ($hasTransitLoss && $hasTransitGain)) {
            $transitRules = [
                'transit_loss_account_id' => ['nullable'],
                'transit_gain_account_id' => ['nullable'],
            ];
        }

        return [
            'ar_account_id' => ['required', 'uuid', $accountExists(), $requireSubtype('accounts_receivable')],
            'ap_account_id' => ['required', 'uuid', $accountExists(), $requireSubtype('accounts_payable')],
            'income_account_id' => ['required', 'uuid', $accountExists(), $requireType('revenue')],
            'expense_account_id' => ['required', 'uuid', $accountExists(), $requireType(['expense', 'cogs', 'asset'])],
            'bank_account_id' => ['required', 'uuid', $accountExists(), $requireSubtype(['bank', 'cash'])],
            'retained_earnings_account_id' => ['required', 'uuid', $accountExists(), $requireSubtype('retained_earnings')],
            'sales_tax_payable_account_id' => ['nullable', 'uuid', $accountExists()],
            'purchase_tax_receivable_account_id' => ['nullable', 'uuid', $accountExists()],
            ...$transitRules,
        ];
    }
}
