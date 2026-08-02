<?php

namespace App\Modules\Payroll\Http\Requests;

use App\Http\Requests\BaseFormRequest;
use App\Services\CurrentCompany;
use Illuminate\Support\Facades\DB;

class VoidPayslipRequest extends BaseFormRequest
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
        return ['reason' => ['required', 'string', 'max:500']];
    }
}
