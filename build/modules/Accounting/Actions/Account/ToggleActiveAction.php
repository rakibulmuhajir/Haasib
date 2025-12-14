<?php

namespace App\Modules\Accounting\Actions\Account;

use App\Contracts\PaletteAction;
use App\Constants\Permissions;
use App\Facades\CompanyContext;
use App\Modules\Accounting\Models\Account;

class ToggleActiveAction implements PaletteAction
{
    public function rules(): array
    {
        return [
            'id' => 'required|string',
            'is_active' => 'required|boolean',
        ];
    }

    public function permission(): ?string
    {
        return Permissions::ACCOUNT_UPDATE;
    }

    public function handle(array $params): array
    {
        $company = CompanyContext::requireCompany();

        $account = Account::where('company_id', $company->id)->findOrFail($params['id']);
        $account->is_active = $params['is_active'];
        $account->save();

        return [
            'message' => "Account {$account->code} is now " . ($account->is_active ? 'active' : 'inactive'),
        ];
    }
}
