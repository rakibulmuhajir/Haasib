<?php

namespace App\Modules\Accounting\Services;

use App\Modules\Accounting\Models\Account;
use App\Modules\Accounting\Models\BankAccount;
use App\Modules\Accounting\Models\IndustryCoaPack;
use App\Modules\Accounting\Models\IndustryCoaTemplate;
use App\Models\Company;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class CompanyBankAccountSyncService
{
    /**
     * Ensure every active bank/cash GL account has a corresponding row in acct.company_bank_accounts.
     *
     * This keeps the Banking module screens aligned with onboarding-created COA accounts and
     * enables bank-feed lookups by gl_account_id.
     *
     * @param array<int, string>|null $onlyGlAccountIds
     * @return array{created:int,linked:int,archived:int}
     */
    public function ensureForCompany(string $companyId, ?string $userId = null, ?array $onlyGlAccountIds = null): array
    {
        return DB::transaction(function () use ($companyId, $userId, $onlyGlAccountIds) {
            /** @var Collection<int, Account> $glAccounts */
            $glAccounts = Account::query()
                ->where('company_id', $companyId)
                ->whereIn('subtype', ['bank', 'cash'])
                ->where('is_active', true)
                ->whereNull('deleted_at')
                ->when($onlyGlAccountIds !== null, fn ($q) => $q->whereIn('id', $onlyGlAccountIds))
                ->orderBy('code')
                ->get(['id', 'code', 'name', 'subtype', 'currency']);

            if ($glAccounts->isEmpty()) {
                return ['created' => 0, 'linked' => 0, 'archived' => 0];
            }

            $existingByGlId = BankAccount::query()
                ->where('company_id', $companyId)
                ->whereNull('deleted_at')
                ->whereIn('gl_account_id', $glAccounts->pluck('id')->all())
                ->pluck('id', 'gl_account_id');

            $hasPrimary = BankAccount::query()
                ->where('company_id', $companyId)
                ->whereNull('deleted_at')
                ->where('is_primary', true)
                ->exists();

            $created = 0;
            foreach ($glAccounts as $index => $glAccount) {
                if ($existingByGlId->has($glAccount->id)) {
                    continue;
                }

                $accountNumber = $this->uniquePlaceholderAccountNumber($companyId, $glAccount->code);

                BankAccount::create([
                    'company_id' => $companyId,
                    'bank_id' => null,
                    'gl_account_id' => $glAccount->id,
                    'account_name' => $glAccount->name,
                    'account_number' => $accountNumber,
                    'account_type' => $glAccount->subtype === 'cash' ? 'cash' : 'checking',
                    'currency' => strtoupper((string) ($glAccount->currency ?: 'PKR')),
                    'opening_balance' => 0,
                    'current_balance' => 0,
                    'is_primary' => ! $hasPrimary && $index === 0,
                    'is_active' => true,
                    'created_by_user_id' => $userId,
                ]);
                $created++;
            }

            $archived = 0;
            if ($onlyGlAccountIds !== null) {
                $archived = $this->archiveSeededBankAccountsIfUserCreatedExist($companyId);
            }

            return ['created' => $created, 'linked' => $glAccounts->count(), 'archived' => $archived];
        });
    }

    /**
     * If the company has user-created bank accounts, archive industry-pack seeded bank/cash ones
     * (only when they have no bank transactions) to reduce clutter.
     */
    public function archiveSeededBankAccountsIfUserCreatedExist(string $companyId): int
    {
        /** @var Company|null $company */
        $company = Company::query()->find($companyId, ['id', 'industry_code', 'industry', 'bank_account_id']);
        $industryCode = $company?->industry_code ?: $company?->industry;
        if (! $company || ! $industryCode) {
            return 0;
        }

        $pack = IndustryCoaPack::query()->where('code', $industryCode)->first(['id']);
        if (! $pack) {
            return 0;
        }

        $templatePairs = IndustryCoaTemplate::query()
            ->where('industry_pack_id', $pack->id)
            ->whereIn('subtype', ['bank', 'cash'])
            ->get(['code', 'name', 'subtype']);

        if ($templatePairs->isEmpty()) {
            return 0;
        }

        $seededGlIds = Account::query()
            ->where('company_id', $companyId)
            ->whereIn('subtype', ['bank', 'cash'])
            ->whereNull('deleted_at')
            ->where(function ($q) use ($templatePairs) {
                foreach ($templatePairs as $tpl) {
                    $q->orWhere(function ($sub) use ($tpl) {
                        $sub->where('code', $tpl->code)
                            ->where('name', $tpl->name)
                            ->where('subtype', $tpl->subtype);
                    });
                }
            })
            ->pluck('id')
            ->all();

        if (empty($seededGlIds)) {
            return 0;
        }

        $hasUserAccounts = BankAccount::query()
            ->where('company_id', $companyId)
            ->whereNull('deleted_at')
            ->where('is_active', true)
            ->whereNotIn('gl_account_id', $seededGlIds)
            ->exists();

        if (! $hasUserAccounts) {
            return 0;
        }

        $archived = 0;

        // If the current primary is seeded and unused, move primary to a user-created account.
        $primary = BankAccount::query()
            ->where('company_id', $companyId)
            ->whereNull('deleted_at')
            ->where('is_primary', true)
            ->first(['id', 'gl_account_id']);

        if ($primary && in_array($primary->gl_account_id, $seededGlIds, true)) {
            $primaryHasTx = DB::table('acct.bank_transactions')
                ->where('bank_account_id', $primary->id)
                ->whereNull('deleted_at')
                ->exists();

            if (! $primaryHasTx) {
                $newPrimary = BankAccount::query()
                    ->where('company_id', $companyId)
                    ->whereNull('deleted_at')
                    ->where('is_active', true)
                    ->whereNotIn('gl_account_id', $seededGlIds)
                    ->orderByDesc('is_primary')
                    ->orderBy('created_at')
                    ->first(['id']);

                if ($newPrimary) {
                    BankAccount::query()->whereKey($newPrimary->id)->update(['is_primary' => true]);
                }
            }
        }

        $seededBankAccounts = BankAccount::query()
            ->where('company_id', $companyId)
            ->whereNull('deleted_at')
            ->where('is_active', true)
            ->whereIn('gl_account_id', $seededGlIds)
            ->get(['id', 'is_primary']);

        foreach ($seededBankAccounts as $bankAccount) {
            if ($bankAccount->is_primary) {
                continue;
            }

            $hasTx = DB::table('acct.bank_transactions')
                ->where('bank_account_id', $bankAccount->id)
                ->whereNull('deleted_at')
                ->exists();

            if ($hasTx) {
                continue;
            }

            BankAccount::query()->whereKey($bankAccount->id)->update(['is_active' => false]);
            $archived++;
        }

        return $archived;
    }

    private function uniquePlaceholderAccountNumber(string $companyId, string $glCode): string
    {
        $base = "ONB-{$glCode}";
        $candidate = $base;
        $suffix = 1;

        while (
            BankAccount::query()
                ->where('company_id', $companyId)
                ->whereNull('deleted_at')
                ->where('account_number', $candidate)
                ->exists()
        ) {
            $suffix++;
            $candidate = "{$base}-{$suffix}";
        }

        return $candidate;
    }
}
