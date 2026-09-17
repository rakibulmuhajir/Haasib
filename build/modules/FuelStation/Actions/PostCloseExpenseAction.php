<?php

namespace App\Modules\FuelStation\Actions;

use App\Contracts\PaletteAction;
use App\Constants\Permissions;
use App\Facades\CompanyContext;
use App\Modules\Accounting\Models\Transaction;
use App\Modules\FuelStation\Services\DailyCloseEntryService;

class PostCloseExpenseAction implements PaletteAction
{
    public function permission(): ?string { return Permissions::DAILY_CLOSE_CREATE; }
    public function rules(): array { return ['close_id' => 'required|uuid', 'account_id' => 'required|uuid', 'amount' => 'required|numeric|min:0.01', 'description' => 'required|string|max:255']; }
    public function handle(array $params): array
    {
        $company = CompanyContext::requireCompany();
        $close = Transaction::where('company_id', $company->id)->where('transaction_type', 'fuel_daily_close')->findOrFail($params['close_id']);
        $transaction = app(DailyCloseEntryService::class)->expense($company->id, $close->transaction_date->toDateString(), $params);
        return ['transaction_id' => $transaction->id];
    }
}
