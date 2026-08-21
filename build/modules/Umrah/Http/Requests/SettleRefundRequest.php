<?php

namespace App\Modules\Umrah\Http\Requests;

use App\Constants\Permissions;
use App\Modules\Accounting\Models\Account;
use App\Modules\Umrah\Models\Refund;
use App\Services\CompanyContextService;
use Closure;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

/**
 * There is no dedicated `refund.settle` permission -- refunds.md's
 * permissions table does not name one, and the task that built this screen
 * says to reuse the settle-side permission an approval already requires
 * rather than invent one silently. Settling reuses
 * Permissions::UMRAH_REFUND_APPROVE, the same permission the accept action
 * already gates, on the basis that both are the accountant/manager-level
 * decision the role-permission table already grants together.
 */
class SettleRefundRequest extends UmrahFormRequest
{
    protected function permission(): string
    {
        return Permissions::UMRAH_REFUND_APPROVE;
    }

    public function rules(): array
    {
        $companyId = app(CompanyContextService::class)->getCompanyId();

        return [
            'settlement_method' => ['required', Rule::in([Refund::SETTLEMENT_CASH, Refund::SETTLEMENT_CREDIT])],
            'account_id' => [
                Rule::requiredIf(fn () => $this->input('settlement_method') === Refund::SETTLEMENT_CASH),
                'nullable',
                'uuid',
                function (string $attribute, mixed $value, Closure $fail) use ($companyId): void {
                    if ($value === null || $value === '') {
                        return;
                    }

                    // Restricted to cash and bank for the same reason the
                    // vendor-credit check below exists: the settle screen
                    // only offers these two subtypes, and the screen not
                    // offering something is not what stops it. A refund
                    // paid out of anything else would post the credit side
                    // against an account that never held the money.
                    if (! Account::query()
                        ->where('company_id', $companyId)
                        ->whereKey($value)
                        ->whereIn('subtype', ['bank', 'cash'])
                        ->where('is_active', true)
                        ->whereNull('deleted_at')
                        ->exists()) {
                        $fail('Select a cash or bank account to pay this refund from.');
                    }
                },
            ],
            'date' => ['nullable', 'date'],
        ];
    }

    /**
     * Defensive server-side check backing the frontend rule that a vendor
     * refund never offers "keep as credit" -- refunds.md is explicit a
     * vendor refund settles by receiving cash only. The frontend not
     * offering the option is not enough on its own; this is what actually
     * stops it if the request is forged.
     */
    public function after(): array
    {
        return [function (Validator $validator): void {
            if ($this->input('settlement_method') !== Refund::SETTLEMENT_CREDIT) {
                return;
            }

            $refund = $this->refund();
            if ($refund && $refund->party_type !== Refund::PARTY_AGENT) {
                $validator->errors()->add('settlement_method', 'A vendor refund can only be settled by receiving cash.');
            }
        }];
    }

    private function refund(): ?Refund
    {
        $companyId = app(CompanyContextService::class)->getCompanyId();
        $refundId = $this->route('refund');

        if (! $companyId || ! $refundId) {
            return null;
        }

        return Refund::where('company_id', $companyId)->find($refundId);
    }
}
