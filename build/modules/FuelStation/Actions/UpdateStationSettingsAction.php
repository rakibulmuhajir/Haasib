<?php

namespace App\Modules\FuelStation\Actions;

use App\Constants\Permissions;
use App\Contracts\PaletteAction;
use App\Modules\FuelStation\Http\Requests\UpdateStationSettingsRequest;
use App\Modules\FuelStation\Services\StationSettingsService;
use App\Services\CurrentCompany;

class UpdateStationSettingsAction implements PaletteAction
{
    public function permission(): ?string
    {
        return Permissions::COMPANY_UPDATE;
    }

    public function rules(): array
    {
        return UpdateStationSettingsRequest::ruleSet();
    }

    public function handle(array $params): array
    {
        app(StationSettingsService::class)->update(app(CurrentCompany::class)->get()->id, $params, auth()->id());

        return ['success' => true];
    }
}
