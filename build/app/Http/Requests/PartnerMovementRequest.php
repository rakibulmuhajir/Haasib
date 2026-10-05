<?php

namespace App\Http\Requests;

use App\Constants\Permissions;
use App\Services\CurrentCompany;
use Illuminate\Validation\Rule;

/**
 * A partner putting money in or taking it out (the page's Invest / Withdraw forms). The same
 * rule set backs the partner.invest and partner.withdraw commands.
 */
class PartnerMovementRequest extends BaseFormRequest
{
    public function authorize(): bool
    {
        return $this->hasCompanyPermission(Permissions::JOURNAL_CREATE) && $this->validateRlsContext();
    }

    protected function prepareForValidation(): void
    {
        // The partner comes from the URL.
        if ($this->route('partner')) {
            $this->merge(['partner_id' => $this->route('partner')]);
        }
    }

    public function rules(): array
    {
        return static::ruleSet();
    }

    /** Plain rule set shared with the commands (never resolve a FormRequest from the container). */
    public static function ruleSet(): array
    {
        $companyId = app(CurrentCompany::class)->get()?->id;

        return [
            'partner_id' => ['required', 'uuid', Rule::exists('auth.partners', 'id')->where('company_id', $companyId)->whereNull('deleted_at')],
            'amount' => ['required', 'numeric', 'min:0.01'],
            'transaction_date' => ['required', 'date_format:Y-m-d'],
            // The cash or bank account the money goes into / comes out of.
            'account_id' => ['required', 'uuid', Rule::exists('acct.accounts', 'id')->where('company_id', $companyId)->whereIn('subtype', ['cash', 'bank'])->whereNull('deleted_at')],
            'description' => ['nullable', 'string', 'max:500'],
            'reference' => ['nullable', 'string', 'max:100'],
        ];
    }
}
