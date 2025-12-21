<?php

namespace App\Modules\Accounting\Services;

use App\Modules\Accounting\Models\Account;
use App\Modules\Accounting\Models\PostingTemplate;
use Illuminate\Validation\ValidationException;

class PostingTemplateValidator
{
    /**
     * @param array<int, array{role:string, account_id:?string}> $lines
     */
    public function validateForSave(string $companyId, string $docType, array $lines): void
    {
        $roleToAccountId = [];
        foreach ($lines as $line) {
            $roleToAccountId[$line['role']] = $line['account_id'] ?? null;
        }

        $missing = [];
        foreach ($this->requiredRoles($docType) as $requiredRoleGroup) {
            // Group allows alternatives (e.g. BANK or CASH)
            $ok = false;
            foreach ($requiredRoleGroup as $role) {
                if (! empty($roleToAccountId[$role])) {
                    $ok = true;
                    break;
                }
            }
            if (! $ok) {
                $missing[] = implode(' or ', $requiredRoleGroup);
            }
        }

        if (! empty($missing)) {
            throw ValidationException::withMessages([
                'lines' => 'Missing required role mappings: ' . implode(', ', $missing),
            ]);
        }

        // Account-level validation: exists + same company + type/subtype expectation (best-effort).
        foreach ($roleToAccountId as $role => $accountId) {
            if (! $accountId) {
                continue;
            }

            $account = Account::where('company_id', $companyId)->where('id', $accountId)->first();
            if (! $account) {
                throw ValidationException::withMessages([
                    'lines' => "Account for role {$role} must belong to the current company.",
                ]);
            }

            $this->validateRoleAccountCompatibility($role, $account);
        }
    }

    public function validateForPosting(PostingTemplate $template, array $roleAccounts, array $requiredWhenAmountsPresent): void
    {
        $missing = [];
        foreach ($requiredWhenAmountsPresent as $role) {
            if (empty($roleAccounts[$role])) {
                $missing[] = $role;
            }
        }

        if (! empty($missing)) {
            throw ValidationException::withMessages([
                'posting' => 'Posting template is missing required role mappings: ' . implode(', ', $missing),
            ]);
        }
    }

    /**
     * @return array<int, array<int, string>> each entry is a group of acceptable roles
     */
    private function requiredRoles(string $docType): array
    {
        return match ($docType) {
            'AR_INVOICE' => [['AR'], ['REVENUE']],
            'AR_PAYMENT' => [['AR'], ['BANK', 'CASH']],
            'AR_CREDIT_NOTE' => [['AR'], ['REVENUE']],
            'AP_BILL' => [['AP'], ['EXPENSE']],
            'AP_PAYMENT' => [['AP'], ['BANK', 'CASH']],
            'AP_VENDOR_CREDIT' => [['AP'], ['EXPENSE']],
            default => [],
        };
    }

    private function validateRoleAccountCompatibility(string $role, Account $account): void
    {
        $ok = match ($role) {
            'AR' => $account->subtype === 'accounts_receivable',
            'AP' => $account->subtype === 'accounts_payable',
            'BANK' => in_array($account->subtype, ['bank', 'cash'], true),
            'CASH' => $account->subtype === 'cash',
            'REVENUE' => $account->type === 'revenue',
            'EXPENSE' => in_array($account->type, ['expense', 'cogs', 'asset'], true),
            'TAX_PAYABLE' => $account->type === 'liability',
            'TAX_RECEIVABLE' => $account->type === 'asset',
            'DISCOUNT_GIVEN' => in_array($account->type, ['expense', 'other_expense', 'revenue'], true),
            'DISCOUNT_RECEIVED' => in_array($account->type, ['revenue', 'other_income'], true),
            default => true,
        };

        if (! $ok) {
            throw ValidationException::withMessages([
                'lines' => "Account {$account->code} is not compatible with role {$role}.",
            ]);
        }
    }
}

