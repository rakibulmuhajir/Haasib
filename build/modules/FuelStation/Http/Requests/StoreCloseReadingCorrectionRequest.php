<?php

namespace App\Modules\FuelStation\Http\Requests;

use App\Constants\Permissions;
use App\Http\Requests\BaseFormRequest;

class StoreCloseReadingCorrectionRequest extends BaseFormRequest
{
    public function authorize(): bool
    {
        return $this->hasCompanyPermission(Permissions::DAILY_CLOSE_CORRECT) && $this->validateRlsContext();
    }

    public function rules(): array
    {
        return static::ruleSet();
    }

    /**
     * Plain rule set shared with CorrectCloseReadingAction.
     *
     * IMPORTANT: this must stay a static/plain method, never resolved by
     * instantiating this FormRequest through the container (app(self::class)).
     * See StoreDailyCloseRequest::ruleSet() for why.
     */
    public static function ruleSet(): array
    {
        return [
            'reading_type' => 'required|in:tank,nozzle',
            'reading_id' => 'required|uuid',
            'corrected_value' => 'required|numeric|min:0',
            'reason' => 'required|string|max:1000',
            'expected_revision' => 'sometimes|integer|min:0',
        ];
    }
}
