<?php

namespace App\Actions\Partner;

use App\Constants\Permissions;
use App\Contracts\PaletteAction;
use App\Facades\CompanyContext;
use App\Http\Requests\PartnerMovementRequest;
use App\Models\Partner;
use App\Services\PartnerLedgerService;

/** partner.invest: a partner puts money in. Dr cash/bank, Cr their Capital account. */
class InvestAction implements PaletteAction, \App\Contracts\DescribesPalette
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
        $result = app(PartnerLedgerService::class)->invest(
            $partner, (float) $params['amount'], $params['transaction_date'], $params['account_id'],
            $params['description'] ?? null, 'page', $params['reference'] ?? null, auth()->id(),
        );

        return [
            'message' => 'Investment recorded',
            'data' => ['id' => $result['transaction']->id, 'gl_transaction_id' => $result['gl']->id, 'warning' => $result['warning']],
        ];
    }

    public function palette(): array
    {
        return [
            'description' => 'Record a partner investment',
            'fields' => [
                'partner_id' => ['hint' => 'Partner'],
                'account_id' => ['inputType' => 'search', 'searchEntity' => 'account', 'hint' => 'Received into account'],
                'transaction_date' => ['hint' => 'Date'],
                'amount' => ['hint' => 'Amount'],
            ],
        ];
    }
}
