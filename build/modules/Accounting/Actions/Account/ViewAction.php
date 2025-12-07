<?php

namespace App\Modules\Accounting\Actions\Account;

use App\Contracts\PaletteAction;
use App\Facades\CompanyContext;
use App\Modules\Accounting\Models\Account;
use App\Support\PaletteFormatter;
use Illuminate\Support\Facades\DB;

class ViewAction implements PaletteAction
{
    public function rules(): array
    {
        return [
            'id' => 'required|string',
        ];
    }

    public function permission(): ?string
    {
        return null;
    }

    public function handle(array $params): array
    {
        $company = CompanyContext::requireCompany();
        $account = $this->resolveAccount($params['id'], $company->id);

        $childCount = Account::where('parent_id', $account->id)->count();

        return [
            'data' => PaletteFormatter::table(
                headers: ['Field', 'Value'],
                rows: [
                    ['Code', $account->code],
                    ['Name', $account->name],
                    ['Type', $account->type],
                    ['Subtype', $account->subtype],
                    ['Normal Balance', $account->normal_balance],
                    ['Currency', $account->currency ?? $company->base_currency],
                    ['Active', $account->is_active ? 'Yes' : 'No'],
                    ['System', $account->is_system ? 'Yes' : 'No'],
                    ['Parent', $account->parent_id ?: '—'],
                    ['Children', $childCount],
                    ['Description', $account->description ?: ''],
                    ['Created', $account->created_at],
                    ['Updated', $account->updated_at],
                ]
            ),
        ];
    }

    private function resolveAccount(string $input, string $companyId): Account
    {
        $query = Account::where('company_id', $companyId);

        if (preg_match('/^[0-9a-fA-F-]{36}$/', $input)) {
            $acc = (clone $query)->where('id', $input)->first();
            if ($acc) {
                return $acc;
            }
        }

        $acc = (clone $query)->where('code', $input)->first();
        if ($acc) {
            return $acc;
        }

        return $query
            ->where(DB::raw('name'), 'ilike', "%{$input}%")
            ->firstOrFail();
    }
}
