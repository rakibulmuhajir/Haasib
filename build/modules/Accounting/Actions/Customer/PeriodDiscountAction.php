<?php

namespace App\Modules\Accounting\Actions\Customer;

use App\Constants\Permissions;
use App\Contracts\PaletteAction;
use App\Modules\Accounting\Services\CustomerPeriodDiscountService;
use App\Services\CurrentCompany;
use Illuminate\Support\Facades\Auth;

class PeriodDiscountAction implements PaletteAction
{
    public function permission(): ?string
    {
        return Permissions::CREDIT_NOTE_CREATE;
    }

    public function rules(): array
    {
        return ['customer_id' => 'required|uuid', 'from' => 'required|date_format:Y-m-d',
            'to' => 'required|date_format:Y-m-d|after_or_equal:from',
            'credit_date' => 'required|date_format:Y-m-d|after_or_equal:to',
            'method' => 'required|in:fixed,percent,per_litre', 'value' => 'required|numeric|gt:0|max:999999999999.99',
            'preview_token' => 'required|string|size:64', 'notes' => 'nullable|string|max:2000'];
    }

    public function handle(array $params): array
    {
        abort_unless(Auth::user()?->hasCompanyPermission(Permissions::CREDIT_NOTE_APPLY), 403);
        if ($params['method'] === 'percent' && (float) $params['value'] > 100) {
            throw \Illuminate\Validation\ValidationException::withMessages(['value' => 'A percentage cannot exceed 100.']);
        }
        $credit = app(CustomerPeriodDiscountService::class)->post(app(CurrentCompany::class)->get(), $params['customer_id'], $params, Auth::id());

        return ['message' => "Period discount {$credit->credit_note_number} posted.", 'data' => ['id' => $credit->id]];
    }
}
