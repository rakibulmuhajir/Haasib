<?php

namespace App\Actions\Partner;

use App\Constants\Permissions;
use App\Contracts\PaletteAction;
use App\Facades\CompanyContext;
use App\Http\Requests\PartnerMovementRequest;
use App\Models\Partner;
use App\Services\PartnerLedgerService;

/**
 * partner.withdraw: a partner takes money out. Dr their Drawings account, Cr cash/bank.
 * Over the drawing limit it still posts and returns a warning.
 */
class WithdrawAction implements PaletteAction
{
    public function rules(): array
    {
        return PartnerMovementRequest::ruleSet();
    }

    public function permission(): ?string
    {
        return Permissions::JOURNAL_CREATE;
    }

    public function handle(array $params): array
    {
        $company = CompanyContext::requireCompany();
        $partner = Partner::where('company_id', $company->id)->findOrFail($params['partner_id']);
        $result = app(PartnerLedgerService::class)->withdraw(
            $partner, (float) $params['amount'], $params['transaction_date'], $params['account_id'],
            $params['description'] ?? null, 'page', $params['reference'] ?? null, auth()->id(),
        );

        return [
            'message' => 'Withdrawal recorded',
            'data' => ['id' => $result['transaction']->id, 'gl_transaction_id' => $result['gl']->id, 'warning' => $result['warning']],
        ];
    }
}
