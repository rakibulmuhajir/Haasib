<?php

namespace App\Modules\FuelStation\Http\Requests;

use App\Http\Requests\BaseFormRequest;
use Illuminate\Support\Carbon;

class CustomerPeriodSummaryRequest extends BaseFormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'from' => ['nullable', 'date_format:Y-m-d'],
            'to' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:from'],
        ];
    }

    /** The requested range, or the last full month when none was asked for. */
    public function range(): array
    {
        $lastMonth = Carbon::now()->subMonthNoOverflow();

        return [
            $this->validated('from') ?? $lastMonth->copy()->startOfMonth()->toDateString(),
            $this->validated('to') ?? $lastMonth->copy()->endOfMonth()->toDateString(),
        ];
    }
}
