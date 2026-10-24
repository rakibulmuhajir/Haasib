<?php

namespace App\Modules\FuelStation\Actions\Amanat;

use App\Constants\Permissions;
use App\Contracts\PaletteAction;
use App\Facades\CompanyContext;
use App\Modules\FuelStation\Http\Requests\ChangeAmanatHolderRequest;
use App\Modules\FuelStation\Services\AmanatCorrectionService;

/** Move an amanat movement to another holder; both balances follow, the books do not change. */
class ChangeHolderAction implements PaletteAction, \App\Contracts\DescribesPalette
{
    public function permission(): ?string
    {
        return Permissions::AMANAT_UPDATE;
    }

    public function rules(): array
    {
        return ['id' => 'required|uuid'] + ChangeAmanatHolderRequest::ruleSet();
    }

    public function handle(array $params): array
    {
        $movement = app(AmanatCorrectionService::class)->changeHolder(
            CompanyContext::requireCompany(), auth()->user(), $params['id'], $params['customer_id'],
        );

        return ['id' => $movement->id];
    }

    public function palette(): array
    {
        return ['hidden' => true];
    }
}
