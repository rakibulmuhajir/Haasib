<?php

namespace App\Modules\Accounting\Actions\AccountingPeriod;

use App\Contracts\PaletteAction;
use App\Constants\Permissions;
use App\Facades\CompanyContext;
use App\Modules\Accounting\Models\AccountingPeriod;
use Illuminate\Support\Facades\Auth;

class ReopenAction implements PaletteAction
{
    public function rules(): array
    {
        return [
            'id' => ['required', 'uuid'],
        ];
    }

    public function permission(): ?string
    {
        return Permissions::JOURNAL_CREATE;
    }

    public function handle(array $params): array
    {
        $company = CompanyContext::requireCompany();

        $period = AccountingPeriod::where('company_id', $company->id)->findOrFail($params['id']);

        if (! $period->is_closed) {
            return ['message' => 'Period is already open.'];
        }

        $period->is_closed = false;
        $period->closed_at = null;
        $period->closed_by_user_id = null;
        $period->updated_by_user_id = Auth::id();
        $period->save();

        return ['message' => 'Period reopened successfully.'];
    }
}

