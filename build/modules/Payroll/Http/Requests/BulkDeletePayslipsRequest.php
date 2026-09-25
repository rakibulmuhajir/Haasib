<?php

namespace App\Modules\Payroll\Http\Requests;

use App\Http\Requests\BaseFormRequest;
use App\Services\CurrentCompany;
use Illuminate\Support\Facades\DB;

/**
 * Deleting more than one payslip at once from the Payslips index (select all/some/none).
 * Same authority check as a single delete or a void - VoidPayslipRequest and
 * DeletePayslipRequest both restrict this to the company owner rather than a
 * Permissions constant, and this bulk path is the same action performed in bulk.
 */
class BulkDeletePayslipsRequest extends BaseFormRequest
{
    public function authorize(): bool
    {
        $company = app(CurrentCompany::class)->get();
        $user = $this->user();

        if (! $company || ! $user || ! $this->validateRlsContext()) {
            return false;
        }

        if ($user->isGodMode()) {
            return true;
        }

        return DB::table('auth.company_user')
            ->where('company_id', $company->id)
            ->where('user_id', $user->id)
            ->where('role', 'owner')
            ->where('is_active', true)
            ->exists();
    }

    public function rules(): array
    {
        return [
            'ids' => ['required', 'array', 'min:1'],
            'ids.*' => ['uuid'],
        ];
    }
}
