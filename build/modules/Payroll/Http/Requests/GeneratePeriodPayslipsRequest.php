<?php

namespace App\Modules\Payroll\Http\Requests;

use App\Constants\Permissions;
use App\Http\Requests\BaseFormRequest;

class GeneratePeriodPayslipsRequest extends BaseFormRequest
{
    public function authorize(): bool
    {
        return $this->hasCompanyPermission(Permissions::PAYSLIP_CREATE)
            && $this->validateRlsContext();
    }

    /**
     * Which month to run, and the day its wages are paid. Both optional: left out, this is the
     * current month paid on its last day, which is what running payroll always did.
     *
     * The month used to be now() and nothing else, so a station entering past months from its
     * register could not run their payroll at all.
     */
    public function rules(): array
    {
        return [
            'month' => ['nullable', 'date_format:Y-m'],
            // Wages are often paid a few days into the next month, so the payment date may fall
            // after the month it pays for, but never before it starts.
            'payment_date' => ['nullable', 'date'],
        ];
    }

    public function withValidator(\Illuminate\Validation\Validator $validator): void
    {
        $validator->after(function ($validator) {
            if (! $this->filled('payment_date')) {
                return;
            }

            $month = $this->input('month') ?: now()->format('Y-m');
            if (! preg_match('/^\d{4}-\d{2}$/', (string) $month)) {
                return; // the month rule reports this
            }

            if ($this->input('payment_date') < $month.'-01') {
                $validator->errors()->add('payment_date', 'Wages cannot be paid before the month they are for begins.');
            }
        });
    }
}
