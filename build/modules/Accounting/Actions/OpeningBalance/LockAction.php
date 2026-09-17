<?php

namespace App\Modules\Accounting\Actions\OpeningBalance;

use App\Constants\Permissions;
use App\Contracts\PaletteAction;
use App\Facades\CompanyContext;
use App\Models\Company;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
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
        return \App\Services\AccountingWriteTransaction::run(fn () => $this->execute($params));
    }

    private function execute(array $params): array
    {
        $contextCompany = CompanyContext::requireCompany();

        return \App\Services\AccountingWriteTransaction::run(function () use ($contextCompany) {
            // Lock order (do not invert): (1) advisory 'opening:'||company_id lock --
            // EXCLUSIVE here, SHARED for ordinary writers via acct.protect_locked_opening
            // -- taken as the very first statement; (2) document row locks (company row
            // below). See the lock-order comment in the protect_locked_openings migration.
            DB::selectOne('select pg_advisory_xact_lock(hashtext(?))', ['opening:'.$contextCompany->id]);
            $company = Company::whereKey($contextCompany->id)->lockForUpdate()->firstOrFail();
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

            // Keep the context-bound Company instance in sync (see SaveAction for why).
            $contextCompany->settings = $settings;

            return ['message' => 'Opening balances locked as of '.$opening['as_of_date']];
        });
    }
}
