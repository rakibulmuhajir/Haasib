<?php

namespace App\Modules\Payroll\Http\Requests;

use App\Constants\Permissions;
use App\Http\Requests\BaseFormRequest;
use App\Modules\Payroll\Models\TimeEntry;
use App\Services\CurrentCompany;
use Illuminate\Validation\Validator;

class StoreTimeEntryRequest extends BaseFormRequest
{
    public function authorize(): bool
    {
        return $this->hasCompanyPermission(Permissions::EMPLOYEE_UPDATE)
            && $this->validateRlsContext();
    }

    public function rules(): array
    {
        return [
            'work_date' => 'required|date|before_or_equal:today',
            'hours' => 'required|numeric|gt:0|max:24',
            'notes' => 'nullable|string|max:255',
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            if ($validator->errors()->has('work_date') || ! $this->filled('work_date')) {
                return;
            }
            $company = app(CurrentCompany::class)->get();
            if (TimeEntry::monthIsLocked($company->id, (string) $this->route('employee'), $this->input('work_date'))) {
                $month = \Illuminate\Support\Carbon::parse($this->input('work_date'))->format('F');
                $validator->errors()->add('work_date', "{$month} is already approved.");
            }
        });
    }
}
