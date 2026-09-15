<?php

namespace App\Modules\Accounting\Actions\OpeningBalance;

use App\Constants\Permissions;
use App\Contracts\PaletteAction;
use App\Facades\CompanyContext;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;

class LockAction implements PaletteAction
{
    public function rules(): array
    {
        return [];
    }

    public function permission(): ?string
    {
        return Permissions::OPENING_BALANCE_MANAGE;
    }

    public function handle(array $params): array
    {
        $company = CompanyContext::requireCompany();
        $settings = $company->settings ?? [];
        $opening = $settings['opening_balances'] ?? null;

        if (! $opening || empty($opening['as_of_date'])) {
            throw ValidationException::withMessages(['as_of_date' => 'Save opening balances before locking them.']);
        }
        if (! empty($opening['locked_at'])) {
            return ['message' => 'Opening balances were already locked.'];
        }

        $opening['locked_at'] = now()->toIso8601String();
        $opening['locked_by_user_id'] = Auth::id();
        $settings['opening_balances'] = $opening;
        $company->settings = $settings;
        $company->save();

        return ['message' => 'Opening balances locked as of '.$opening['as_of_date']];
    }
}
