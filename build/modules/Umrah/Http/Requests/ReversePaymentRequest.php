<?php

namespace App\Modules\Umrah\Http\Requests;

use App\Constants\Permissions;
use App\Services\CompanyContextService;
use Illuminate\Support\Facades\DB;

class ReversePaymentRequest extends UmrahFormRequest
{
    public function authorize(): bool
    {
        if (! parent::authorize()) {
            return false;
        }

        return DB::table('auth.company_user')
            ->where('company_id', app(CompanyContextService::class)->getCompanyId())
            ->where('user_id', $this->user()?->id)
            ->where('is_active', true)
            ->value('role') !== 'agent';
    }

    protected function permission(): string
    {
        return Permissions::UMRAH_PAYMENT_REVERSE;
    }

    public function rules(): array
    {
        return ['reason' => ['required', 'string', 'min:5', 'max:1000']];
    }
}
