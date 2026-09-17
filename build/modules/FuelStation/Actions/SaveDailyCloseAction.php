<?php

namespace App\Modules\FuelStation\Actions;

use App\Contracts\PaletteAction;
use App\Constants\Permissions;
use App\Facades\CompanyContext;
use App\Modules\FuelStation\Services\DailyCloseService;
use App\Modules\FuelStation\Services\DailyCloseReconciliationService;

class SaveDailyCloseAction implements PaletteAction
{
    public function permission(): ?string { return Permissions::DAILY_CLOSE_CREATE; }
    public function rules(): array { return \App\Modules\FuelStation\Http\Requests\StoreDailyCloseRequest::ruleSet(); }
    public function handle(array $params): array
    {
        $company = CompanyContext::requireCompany();
        $user = auth()->user();
        if (($params['intent'] ?? 'post') === 'park') {
            app(DailyCloseReconciliationService::class)->park($company->id, $params, $user->id);
            return ['parked' => true];
        }
        return app(DailyCloseService::class)->processDailyClose($company->id, $params, $user);
    }
}
