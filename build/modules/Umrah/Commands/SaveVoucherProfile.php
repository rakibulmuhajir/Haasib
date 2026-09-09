<?php

namespace App\Modules\Umrah\Commands;

use App\Models\Company;
use App\Modules\Umrah\Models\Agent;
use App\Modules\Umrah\Models\VisaVendor;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class SaveVoucherProfile
{
    public function __construct(public readonly string $companyId, public readonly array $data) {}

    public function handle(): void
    {
        DB::transaction(function (): void {
            if ($this->data['target'] === 'company') {
                $company = Company::whereKey($this->companyId)->lockForUpdate()->firstOrFail();
                $company->update(['settings' => [...($company->settings ?? []), 'umrah_voucher' => $this->data['details']]]);

                return;
            }
            [$type, $id] = explode(':', $this->data['target'], 2);
            $class = $type === 'agent' ? Agent::class : VisaVendor::class;
            $record = $class::where('company_id', $this->companyId)->where('is_active', true)->lockForUpdate()->find($id);
            if (! $record) {
                throw ValidationException::withMessages(['target' => 'Choose an active agent or provider in this company.']);
            }
            $record->update(['voucher_settings' => $this->data['details']]);
        });
    }
}
