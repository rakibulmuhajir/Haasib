<?php

namespace App\Actions\Partner;

use App\Constants\Permissions;
use App\Contracts\PaletteAction;
use App\Facades\CompanyContext;
use App\Http\Requests\ShareProfitRequest;
use App\Services\PartnerProfitShareService;

/** partner.share_profit: share a month's profit between the partners (dry_run only previews). */
class ShareProfitAction implements PaletteAction
{
    public function rules(): array
    {
        return ShareProfitRequest::ruleSet();
    }

    public function permission(): ?string
    {
        return Permissions::JOURNAL_CREATE;
    }

    public function handle(array $params): array
    {
        $company = CompanyContext::requireCompany();
        $service = app(PartnerProfitShareService::class);
        $result = ! empty($params['dry_run']) ? $service->preview($company->id, $params['month']) : $service->share($company->id, $params['month']);

        return ['message' => $result['message'] ?? 'Profit shared', 'data' => $result];
    }
}
