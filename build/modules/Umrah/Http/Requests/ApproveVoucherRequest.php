<?php

namespace App\Modules\Umrah\Http\Requests;

use App\Constants\Permissions;
use App\Modules\Umrah\Models\Agent;
use App\Services\CompanyContextService;
use Illuminate\Support\Facades\DB;

class ApproveVoucherRequest extends UmrahFormRequest
{
    public function authorize(): bool
    {
        if (! parent::authorize()) return false;
        $companyId = app(CompanyContextService::class)->getCompanyId();
        $role = DB::table('auth.company_user')->where('company_id', $companyId)->where('user_id', $this->user()?->id)->where('is_active', true)->value('role');
        if ($role !== 'member') return true;
        return Agent::where('company_id', $companyId)->where('user_id', $this->user()?->id)->where('can_approve_voucher', true)->where('is_active', true)->exists();
    }
    protected function permission(): string { return Permissions::UMRAH_VOUCHER_APPROVE; }
    public function rules(): array { return []; }
}
