<?php

namespace App\Modules\Accounting\Actions\Account;

use App\Contracts\PaletteAction;
use App\Constants\Permissions;
use App\Facades\CompanyContext;
use App\Modules\Accounting\Models\Account;
use Illuminate\Support\Facades\DB;

class DeleteAction implements PaletteAction
{
    public function rules(): array
    {
        return [
            'id' => 'required|string',
        ];
    }

    public function permission(): ?string
    {
        return Permissions::ACCOUNT_DELETE;
    }

    public function handle(array $params): array
    {
        $company = CompanyContext::requireCompany();
        $account = Account::where('company_id', $company->id)->findOrFail($params['id']);

        if ($account->is_system) {
            throw new \InvalidArgumentException('Cannot delete system account');
        }

        $hasChildren = Account::where('parent_id', $account->id)->exists();
        if ($hasChildren) {
            throw new \InvalidArgumentException('Cannot delete account with children');
        }

        $usedInBills = DB::table('acct.bill_line_items')->where('account_id', $account->id)->exists();
        if ($usedInBills) {
            throw new \InvalidArgumentException('Cannot delete account used in bill line items');
        }

        $account->delete();

        return [
            'message' => "Account {$account->code} deleted",
        ];
    }
}
