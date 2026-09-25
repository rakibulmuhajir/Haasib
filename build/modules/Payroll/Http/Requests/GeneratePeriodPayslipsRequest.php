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
     * Which month to run. Optional: left out, this is the current month.
     *
     * The month used to be now() and nothing else, so a station entering past months from its
     * register could not run their payroll at all.
     *
     * There is no fixed pay day, so the UI no longer sends payment_date - wages are paid
     * whenever they are paid. The field is kept, nullable, for any other caller that still sends
     * one; the controller fills it with the month's last day when it is missing, purely to
     * satisfy the payroll_periods table's not-null column.
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
