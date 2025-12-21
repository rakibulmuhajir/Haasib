<?php

namespace App\Modules\Accounting\Actions\AccountingPeriod;

use App\Contracts\PaletteAction;
use App\Constants\Permissions;
use App\Facades\CompanyContext;
use App\Modules\Accounting\Models\AccountingPeriod;
use App\Modules\Accounting\Models\Transaction;
use Illuminate\Support\Facades\Auth;

class CloseAction implements PaletteAction
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

        if ($period->is_closed) {
            return ['message' => 'Period already closed.'];
        }

        $hasNonPosted = Transaction::where('company_id', $company->id)
            ->where('period_id', $period->id)
            ->where('status', '!=', 'posted')
            ->whereNull('deleted_at')
            ->exists();

        if ($hasNonPosted) {
            throw new \InvalidArgumentException('Cannot close a period with non-posted transactions.');
        }

        $period->is_closed = true;
        $period->closed_at = now();
        $period->closed_by_user_id = Auth::id();
        $period->updated_by_user_id = Auth::id();
        $period->save();

        return ['message' => 'Period closed successfully.'];
    }
}

