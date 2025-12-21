<?php

namespace App\Modules\Accounting\Services;

use App\Models\Company;
use App\Modules\Accounting\Models\PostingTemplate;
use App\Modules\Accounting\Models\PostingTemplateLine;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class PostingTemplateInstaller
{
    /**
     * Ensure minimal default posting templates exist for a company.
     * Intended as a bootstrap until a full template-management UI exists.
     */
    public function ensureDefaults(Company $company): void
    {
        if (! Schema::hasTable('acct.posting_templates')) {
            return;
        }

        DB::transaction(function () use ($company) {
            $userId = Auth::id();

            $this->ensureTemplate($company, 'AR_INVOICE', 'Default AR Invoice', [
                'AR' => $company->ar_account_id,
                'REVENUE' => $company->income_account_id,
                'TAX_PAYABLE' => $company->sales_tax_payable_account_id,
                'DISCOUNT_GIVEN' => $company->expense_account_id, // fallback (contra revenue ideally)
            ], $userId);

            $this->ensureTemplate($company, 'AR_PAYMENT', 'Default AR Payment', [
                'AR' => $company->ar_account_id,
                'BANK' => $company->bank_account_id,
            ], $userId);

            $this->ensureTemplate($company, 'AR_CREDIT_NOTE', 'Default AR Credit Note', [
                'AR' => $company->ar_account_id,
                'REVENUE' => $company->income_account_id,
                'TAX_PAYABLE' => $company->sales_tax_payable_account_id,
            ], $userId);

            $this->ensureTemplate($company, 'AP_BILL', 'Default AP Bill', [
                'AP' => $company->ap_account_id,
                'EXPENSE' => $company->expense_account_id,
                'TAX_RECEIVABLE' => $company->purchase_tax_receivable_account_id,
                'DISCOUNT_RECEIVED' => $company->income_account_id, // fallback (other_income ideally)
            ], $userId);

            $this->ensureTemplate($company, 'AP_PAYMENT', 'Default AP Payment', [
                'AP' => $company->ap_account_id,
                'BANK' => $company->bank_account_id,
            ], $userId);

            $this->ensureTemplate($company, 'AP_VENDOR_CREDIT', 'Default AP Vendor Credit', [
                'AP' => $company->ap_account_id,
                'EXPENSE' => $company->expense_account_id,
                'TAX_RECEIVABLE' => $company->purchase_tax_receivable_account_id,
            ], $userId);
        });
    }

    /**
     * @param array<string, string|null> $roleToAccountId
     */
    private function ensureTemplate(Company $company, string $docType, string $name, array $roleToAccountId, ?string $userId): void
    {
        $template = PostingTemplate::where('company_id', $company->id)
            ->where('doc_type', $docType)
            ->where('is_default', true)
            ->where('is_active', true)
            ->whereNull('deleted_at')
            ->first();

        if (! $template) {
            // Ensure single default per doc type
            PostingTemplate::where('company_id', $company->id)
                ->where('doc_type', $docType)
                ->update(['is_default' => false]);

            $template = PostingTemplate::create([
                'company_id' => $company->id,
                'doc_type' => $docType,
                'name' => $name,
                'description' => 'Auto-created during onboarding / first posting.',
                'is_active' => true,
                'is_default' => true,
                'version' => 1,
                'effective_from' => now()->toDateString(),
                'effective_to' => null,
                'created_by_user_id' => $userId,
                'updated_by_user_id' => $userId,
            ]);
        }

        $precedence = 1;
        foreach ($roleToAccountId as $role => $accountId) {
            if (! $accountId) {
                continue;
            }

            PostingTemplateLine::updateOrCreate(
                ['template_id' => $template->id, 'role' => $role],
                [
                    'account_id' => $accountId,
                    'description' => null,
                    'precedence' => $precedence++,
                    'is_required' => in_array($role, ['AR', 'AP', 'REVENUE', 'EXPENSE', 'BANK', 'CASH'], true),
                ]
            );
        }
    }
}

