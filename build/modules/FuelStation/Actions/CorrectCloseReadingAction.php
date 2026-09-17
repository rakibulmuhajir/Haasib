<?php

namespace App\Modules\FuelStation\Actions;

use App\Contracts\PaletteAction;
use App\Constants\Permissions;
use App\Facades\CompanyContext;
use App\Modules\FuelStation\Http\Requests\StoreCloseReadingCorrectionRequest;

/**
 * Records a correction against a tank or nozzle reading that belongs to an
 * already-posted (snapshot) Daily Close. The original reading row is never
 * touched -- a posted close's physical observations are immutable by design
 * (see fuel.capture_post_close_activity). This inserts an append-only
 * correction row that DailyCloseReconciliationService::view() applies on top
 * of the CURRENT/RECONCILED section only; the POSTED SNAPSHOT section stays
 * byte-identical.
 */
class CorrectCloseReadingAction implements PaletteAction
{
    public function permission(): ?string
    {
        return Permissions::DAILY_CLOSE_CORRECT;
    }

    public function rules(): array
    {
        return StoreCloseReadingCorrectionRequest::ruleSet() + ['close_id' => 'required|uuid'];
    }

    public function handle(array $params): array
    {
        return app(\App\Modules\FuelStation\Services\CloseReadingCorrectionService::class)->correct(
            CompanyContext::requireCompany()->id, $params, auth()->id()
        );
    }
}
