<?php

namespace App\Modules\FuelStation\Http\Requests;

use App\Constants\Permissions;
use App\Http\Requests\BaseFormRequest;

class UnlockDailyCloseRequest extends BaseFormRequest
{
    public function authorize(): bool
    {
        return $this->hasCompanyPermission(Permissions::DAILY_CLOSE_UNLOCK)
            && $this->validateRlsContext();
    }

    public function rules(): array
    {
        return [
            // Reopening a settled day is the one action an auditor will always ask about.
            // The reason is kept forever in fuel.daily_close_unlocks; a bare "fix" is no
            // answer, so require enough of a sentence to be worth reading later.
            'reason' => ['required', 'string', 'min:10', 'max:500'],
        ];
    }

    public function messages(): array
    {
        return [
            'reason.required' => 'Say why this day is being reopened. It is kept on the permanent record.',
            'reason.min' => 'Give a fuller reason — this is what an auditor will read later.',
        ];
    }
}
