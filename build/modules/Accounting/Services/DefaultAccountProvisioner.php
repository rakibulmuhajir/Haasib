<?php

namespace App\Modules\Accounting\Services;

use App\Models\Company;
use App\Modules\Accounting\Models\Account;
use Illuminate\Support\Facades\Schema;

class DefaultAccountProvisioner
{
    public function ensureCoreDefaults(Company $company): array
    {
        $updates = [];
        $hasSystemIdentifier = Schema::connection('pgsql')->hasColumn('acct.accounts', 'system_identifier');

        if (! $company->ar_account_id) {
            $arAccount = null;
            if ($hasSystemIdentifier) {
                $arAccount = Account::where('company_id', $company->id)
                    ->where('system_identifier', 'ar_control')
                    ->first();
            }
            if (! $arAccount) {
                $arAccount = Account::where('company_id', $company->id)
                    ->where('subtype', 'accounts_receivable')
                    ->orderBy('code')
                    ->first();
            }
            if ($arAccount) {
                $updates['ar_account_id'] = $arAccount->id;
            }
        }

        if (! $company->ap_account_id) {
            $apAccount = null;
            if ($hasSystemIdentifier) {
                $apAccount = Account::where('company_id', $company->id)
                    ->where('system_identifier', 'ap_control')
                    ->first();
            }
            if (! $apAccount) {
                $apAccount = Account::where('company_id', $company->id)
                    ->where('subtype', 'accounts_payable')
                    ->orderBy('code')
                    ->first();
            }
            if ($apAccount) {
                $updates['ap_account_id'] = $apAccount->id;
            }
        }

        if (! $company->income_account_id) {
            $incomeAccount = null;
            if ($hasSystemIdentifier) {
                $incomeAccount = Account::where('company_id', $company->id)
                    ->where('system_identifier', 'primary_revenue')
                    ->first();
            }
            if (! $incomeAccount) {
                $incomeAccount = Account::where('company_id', $company->id)
                    ->where('type', 'revenue')
                    ->orderBy('code')
                    ->first();
            }
            if ($incomeAccount) {
                $updates['income_account_id'] = $incomeAccount->id;
            }
        }

        if (! $company->expense_account_id) {
            $expenseAccount = Account::where('company_id', $company->id)
                ->where('type', 'expense')
                ->orderBy('code')
                ->first();
            if ($expenseAccount) {
                $updates['expense_account_id'] = $expenseAccount->id;
            }
        }

        if (! $company->bank_account_id) {
            $bankAccount = Account::where('company_id', $company->id)
                ->whereIn('subtype', ['bank', 'cash'])
                ->orderByRaw("CASE WHEN subtype = 'bank' THEN 0 ELSE 1 END")
                ->orderBy('code')
                ->first();
            if ($bankAccount) {
                $updates['bank_account_id'] = $bankAccount->id;
            }
        }

        if (! $company->retained_earnings_account_id) {
            $retainedEarnings = null;
            if ($hasSystemIdentifier) {
                $retainedEarnings = Account::where('company_id', $company->id)
                    ->where('system_identifier', 'retained_earnings')
                    ->first();
            }
            if (! $retainedEarnings) {
                $retainedEarnings = Account::where('company_id', $company->id)
                    ->where('subtype', 'retained_earnings')
                    ->orderBy('code')
                    ->first();
            }
            if ($retainedEarnings) {
                $updates['retained_earnings_account_id'] = $retainedEarnings->id;
            }
        }

        if (! empty($updates)) {
            $company->update($updates);
        }

        return $updates;
    }

    public function ensureTransitAccounts(Company $company): array
    {
        $lossAccount = Account::where('company_id', $company->id)
            ->where(function ($query) {
                $query->where('code', '8060')
                    ->orWhere('name', 'Transit Loss');
            })
            ->first();

        if (! $lossAccount) {
            $lossAccount = Account::create([
                'company_id' => $company->id,
                'code' => $this->nextAvailableCode($company->id, 8060, 8099),
                'name' => 'Transit Loss',
                'type' => 'other_expense',
                'subtype' => 'other_expense',
                'normal_balance' => 'debit',
                'currency' => null,
                'is_contra' => false,
                'is_active' => true,
                'is_system' => false,
                'description' => 'Short receipts or delivery variances recorded at receiving.',
            ]);
        }

        $gainAccount = Account::where('company_id', $company->id)
            ->where(function ($query) {
                $query->where('code', '7050')
                    ->orWhere('name', 'Transit Gain');
            })
            ->first();

        if (! $gainAccount) {
            $gainAccount = Account::create([
                'company_id' => $company->id,
                'code' => $this->nextAvailableCode($company->id, 7050, 7099),
                'name' => 'Transit Gain',
                'type' => 'other_income',
                'subtype' => 'other_income',
                'normal_balance' => 'credit',
                'currency' => null,
                'is_contra' => false,
                'is_active' => true,
                'is_system' => false,
                'description' => 'Over receipts or delivery gains recorded at receiving.',
            ]);
        }

        $updates = [];
        $hasTransitLoss = Schema::connection('pgsql')->hasColumn('auth.companies', 'transit_loss_account_id');
        $hasTransitGain = Schema::connection('pgsql')->hasColumn('auth.companies', 'transit_gain_account_id');

        if ($hasTransitLoss && ! $company->transit_loss_account_id) {
            $updates['transit_loss_account_id'] = $lossAccount->id;
        }
        if ($hasTransitGain && ! $company->transit_gain_account_id) {
            $updates['transit_gain_account_id'] = $gainAccount->id;
        }
        if (! empty($updates)) {
            $company->update($updates);
        }

        return [
            'transit_loss_account_id' => $lossAccount->id,
            'transit_gain_account_id' => $gainAccount->id,
        ];
    }

    private function nextAvailableCode(string $companyId, int $startCode, int $endCode): string
    {
        for ($code = $startCode; $code <= $endCode; $code++) {
            $exists = Account::where('company_id', $companyId)
                ->where('code', (string) $code)
                ->exists();

            if (! $exists) {
                return (string) $code;
            }
        }

        return (string) $startCode;
    }
}
