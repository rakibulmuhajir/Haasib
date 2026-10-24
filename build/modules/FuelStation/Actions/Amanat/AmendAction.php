<?php

namespace App\Modules\FuelStation\Actions\Amanat;

use App\Constants\Permissions;
use App\Contracts\PaletteAction;
use App\Facades\CompanyContext;
use App\Modules\FuelStation\Http\Requests\AmendAmanatMovementRequest;
use App\Modules\FuelStation\Services\AmanatCorrectionService;

/** Correct a standalone amanat deposit or withdrawal: the original is reversed and voided, a corrected one posted. */
class AmendAction implements PaletteAction, \App\Contracts\DescribesPalette
{
    public function permission(): ?string
    {
        return Permissions::AMANAT_UPDATE;
    }

    public function rules(): array
    {
        return ['id' => 'required|uuid'] + AmendAmanatMovementRequest::ruleSet();
    }

    public function handle(array $params): array
    {
        $replacement = app(AmanatCorrectionService::class)->amend(
            CompanyContext::requireCompany(), auth()->user(), $params['id'], $params,
        );

        return ['id' => $replacement->id];
    }

    public function palette(): array
    {
        return ['hidden' => true];
    }
}
