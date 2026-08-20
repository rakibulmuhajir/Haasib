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
                // The accounts this call was told about are the ones somebody
                // just asked for, so they are the last things that should be
                // tidied away. Passing them through as protected is what stops
                // the archive step below from undoing the work above.
                $archived = $this->archiveSeededBankAccountsIfUserCreatedExist($companyId, $onlyGlAccountIds);
            }

            return ['created' => $created, 'linked' => $glAccounts->count(), 'archived' => $archived];
        });
    }

    /**
     * Does this account hold anything worth keeping on screen?
     *
     * Archiving is a decluttering measure, so the only accounts it may touch are
     * empty ones. The test used to be "has no rows in bank_transactions", which
     * asks whether a bank *feed* has ever been imported -- a different question
     * entirely. An account posted to entirely through journals has no feed rows
     * at all, so a fuel station's operating account holding nine million rupees
     * across forty-five entries read as unused and was archived, after which the
     * dashboard's cash position quietly excluded it and reported the petty cash
     * float as the whole of the company's money.
     *
     * Activity therefore means any of: a bank feed row, a posting against the GL
     * account behind it, or simply a balance that is not zero.
     */
    protected function hasActivity(BankAccount $bankAccount): bool
    {
        if ((float) $bankAccount->current_balance !== 0.0 || (float) $bankAccount->opening_balance !== 0.0) {
            return true;
        }

        $hasFeed = DB::table('acct.bank_transactions')
            ->where('bank_account_id', $bankAccount->id)
            ->whereNull('deleted_at')
            ->exists();

        if ($hasFeed) {
            return true;
        }

        return $bankAccount->gl_account_id !== null
            && DB::table('acct.journal_entries')
                ->where('account_id', $bankAccount->gl_account_id)
                ->exists();
    }

    /**
     * If the company has user-created bank accounts, archive industry-pack seeded bank/cash ones
     * (only when they are empty) to reduce clutter.
     *
     * "Seeded" is inferred from the GL account matching an industry template on
     * code, name and subtype -- which is a coincidence, not a provenance. A
     * company that names its first account "Operating Bank Account" gets code
     * 1000 from the next-available-code allocator, and that is character for
     * character the fuel pack's own first template. The account was therefore
     * archived in the same breath as it was created, and it took the company's
     * cash position off the dashboard with it.
     *
     * $protectGlAccountIds names accounts the caller has just created or
     * updated. Whatever they look like, they are wanted.
     *
     * @param array<int, string> $protectGlAccountIds
     */
    public function archiveSeededBankAccountsIfUserCreatedExist(string $companyId, array $protectGlAccountIds = []): int
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

        $seededGlIds = array_values(array_diff($seededGlIds, $protectGlAccountIds));

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
            ->first(['id', 'gl_account_id', 'current_balance', 'opening_balance']);

        if ($primary && in_array($primary->gl_account_id, $seededGlIds, true)) {
            // Same test as the archive loop below, for the same reason: a feed
            // row is not the only evidence an account is in use.
            if (! $this->hasActivity($primary)) {
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
            ->get(['id', 'is_primary', 'gl_account_id', 'current_balance', 'opening_balance']);

        foreach ($seededBankAccounts as $bankAccount) {
            if ($bankAccount->is_primary) {
                continue;
            }

            if ($this->hasActivity($bankAccount)) {
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
