<?php

namespace App\Modules\Accounting\Actions\Sale;

use App\Constants\Permissions;
use App\Contracts\DescribesPalette;
use App\Contracts\PaletteAction;
use App\Facades\CompanyContext;
use App\Modules\Accounting\Models\Account;
use App\Modules\Accounting\Services\CashSaleService;
use App\Support\CompanyClock;
use App\Support\PaletteFormatter;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;

/**
 * A cash sale in one line: an invoice to the Cash Sales customer, settled at once.
 * Same write path as the sales page (CashSaleService). Needs both invoice.create and
 * payment.create, like the page's request does.
 */
class CreateAction implements PaletteAction, DescribesPalette
{
    public function rules(): array
    {
        return [
            'date' => 'nullable|date',
            'amount' => 'required|numeric|min:0.01|max:999999999.99',
            'description' => 'nullable|string|max:500',
            'income_account_id' => 'nullable|uuid',
            'deposit_account_id' => 'nullable|uuid',
        ];
    }

    public function permission(): ?string
    {
        return Permissions::INVOICE_CREATE;
    }

    public function handle(array $params): array
    {
        $company = CompanyContext::requireCompany();

        $user = Auth::user();
        if ($user && method_exists($user, 'hasCompanyPermission') && ! $user->hasCompanyPermission(Permissions::PAYMENT_CREATE)) {
            throw new AuthorizationException('Permission denied: '.Permissions::PAYMENT_CREATE);
        }

        $depositId = $params['deposit_account_id'] ?? null;
        if ($depositId) {
            $deposit = Account::where('company_id', $company->id)->where('is_active', true)
                ->whereNull('deleted_at')->whereIn('subtype', ['bank', 'cash'])->find($depositId);
            if (! $deposit) {
                throw ValidationException::withMessages(['deposit_account_id' => 'Choose a cash or bank account of this company.']);
            }
        } else {
            // The sales page's default: the company's bank account, else the first bank or cash account.
            $depositId = $company->bank_account_id
                ?: Account::where('company_id', $company->id)->whereIn('subtype', ['bank', 'cash'])
                    ->where('is_active', true)->whereNull('deleted_at')->orderBy('code')->value('id');
            if (! $depositId) {
                throw ValidationException::withMessages(['deposit_account_id' => 'Add a cash or bank account first.']);
            }
        }

        $incomeId = $params['income_account_id'] ?? null;
        if ($incomeId && ! Account::where('company_id', $company->id)->where('type', 'revenue')
            ->where('is_active', true)->whereNull('deleted_at')->whereKey($incomeId)->exists()) {
            throw ValidationException::withMessages(['income_account_id' => 'Choose an income account of this company.']);
        }

        $date = $params['date'] ?? app(CompanyClock::class)->today();
        $amount = round((float) $params['amount'], 2);

        $result = app(CashSaleService::class)->record($company, $user, $date, $depositId, [[
            'description' => ($params['description'] ?? '') !== '' ? $params['description'] : 'Cash sale',
            'amount' => $amount,
            'income_account_id' => $incomeId,
        ]]);

        return [
            'message' => 'Sale recorded: '.PaletteFormatter::money($amount, $company->base_currency),
            'data' => [
                'id' => $result['invoice_id'],
                'invoice_id' => $result['invoice_id'],
                'payment_id' => $result['payment_result']['data']['id'] ?? null,
            ],
            'redirect' => $result['invoice_id'] ? "/{$company->slug}/invoices/{$result['invoice_id']}" : null,
        ];
    }

    public function palette(): array
    {
        return [
            'description' => 'Record a cash sale',
            'fields' => [
                'amount' => ['hint' => 'Amount'],
                'description' => ['hint' => 'What was sold'],
                'deposit_account_id' => ['inputType' => 'search', 'searchEntity' => 'account', 'hint' => 'Received into account'],
                'date' => ['hint' => 'Date'],
            ],
        ];
    }
}
