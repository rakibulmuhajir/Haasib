<?php

namespace App\Modules\Payroll\Actions;

use App\Constants\Permissions;
use App\Contracts\PaletteAction;
use App\Models\User;
use App\Modules\Accounting\Models\Account;
use App\Modules\Payroll\Models\Employee;
use App\Modules\Payroll\Services\PayrollPostingService;
use App\Services\CurrentCompany;
use Illuminate\Validation\Rule;

class CreateSalaryAdvanceAction implements PaletteAction
{
    public function __construct(private readonly PayrollPostingService $postingService) {}

    public function rules(): array
    {
        $company = app(CurrentCompany::class)->getOrFail();

        return [
            'employee_id' => [
                'required',
                'uuid',
                Rule::exists(Employee::class, 'id')
                    ->where('company_id', $company->id)
                    ->where('is_active', true)
                    ->where('employment_status', 'active'),
            ],
            'advance_date' => ['required', 'date'],
            'amount' => ['required', 'numeric', 'min:0.01', 'max:9999999999999.99'],
            'payment_method' => ['required', 'string', Rule::in(['cash', 'bank_transfer', 'cheque'])],
            'bank_account_id' => [
                'nullable',
                'uuid',
                Rule::exists(Account::class, 'id')
                    ->where('company_id', $company->id)
                    ->whereIn('subtype', ['bank', 'cash'])
                    ->where('is_active', true),
            ],
            'reference' => ['nullable', 'string', 'max:100'],
            'reason' => ['nullable', 'string', 'max:255'],
            'notes' => ['nullable', 'string'],
            'recorded_by_user_id' => ['required', 'uuid', Rule::exists(User::class, 'id')],
        ];
    }

    public function permission(): ?string
    {
        return Permissions::PAYSLIP_CREATE;
    }

    public function handle(array $params): array
    {
        $company = app(CurrentCompany::class)->getOrFail();
        $advance = $this->postingService->createSalaryAdvance(
            $params,
            $company->id,
            $params['recorded_by_user_id'],
            $company->base_currency
        );

        return [
            'message' => 'Salary advance recorded.',
            'data' => ['id' => $advance->id],
            'redirect' => "/{$company->slug}/salary-advances",
        ];
    }
}
