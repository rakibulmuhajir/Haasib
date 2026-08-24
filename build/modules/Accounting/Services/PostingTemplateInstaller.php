<?php

namespace App\Modules\Accounting\Services;

use App\Models\Company;
use App\Modules\Accounting\Models\Account;
use App\Modules\Accounting\Models\PostingTemplate;
use App\Modules\Accounting\Models\PostingTemplateLine;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Carbon;

class PostingTemplateInstaller
{
    private const DEFAULT_EFFECTIVE_FROM = '2000-01-01';

    /**
     * Ensure minimal default posting templates exist for a company.
     * Intended as a bootstrap until a full template-management UI exists.
     */
    public function ensureDefaults(Company $company): void
    {
        if (! Schema::hasTable('acct.posting_templates') && ! Schema::hasTable('posting_templates')) {
            return;
        }

        DB::transaction(function () use ($company) {
            $userId = Auth::id();
            $discountReceivedAccountId = $this->resolveDiscountReceivedAccountId($company);

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
                'DISCOUNT_RECEIVED' => $discountReceivedAccountId ?? $company->income_account_id, // fallback (other_income ideally)
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

            $this->ensureTicketTemplates($company, $userId);
        });
    }

    /**
     * The four ticket templates, kept apart from the six above because they are
     * conditional. Their roles are backed by dedicated accounts rather than the
     * company's *_account_id columns, and a company whose chart of accounts has
     * no ticket accounts is a company not running the module -- installing a
     * template with no lines for it would replace "no template configured" with
     * the far more confusing "template is missing the CLEARING role mapping".
     * So when the accounts are absent, nothing is written at all.
     */
    private function ensureTicketTemplates(Company $company, ?string $userId): void
    {
        $ticket = $this->resolveTicketAccountIds($company);

        // Every ticket posting needs the clearing account: it is the account the
        // supplier side of a ticket sits in between invoice and bill.
        if (empty($ticket['2350'])) {
            return;
        }

        $this->ensureTemplate($company, 'TICKET_INVOICE', 'Default Ticket Invoice', [
            'AR' => $company->ar_account_id,
            'CLEARING' => $ticket['2350'],
            'REVENUE' => $ticket['4130'],
            'SERVICE_FEE' => $ticket['4140'],
            'DISCOUNT_GIVEN' => $ticket['4150'],
            'ROUNDING' => $ticket['9900'],
        ], $userId);

        $this->ensureTemplate($company, 'TICKET_BILL', 'Default Ticket Bill', [
            'AP' => $company->ap_account_id,
            'CLEARING' => $ticket['2350'],
        ], $userId);

        $this->ensureTemplate($company, 'TICKET_CREDIT_NOTE', 'Default Ticket Credit Note', [
            'AR' => $company->ar_account_id,
            'CANCELLATION_ADJUSTMENT' => $ticket['4160'],
        ], $userId);

        $this->ensureTemplate($company, 'TICKET_VENDOR_CREDIT', 'Default Ticket Vendor Credit', [
            'AP' => $company->ap_account_id,
            'CANCELLATION_ADJUSTMENT' => $ticket['4160'],
        ], $userId);
    }

    /**
     * The six accounts 2026_08_23_000001_add_ticket_accounts_to_existing_companies
     * installs, by code. Codes rather than names because a company may rename an
     * account but the code is what the COA pack keys on.
     *
     * @return array<string, string|null>
     */
    private function resolveTicketAccountIds(Company $company): array
    {
        $codes = ['2350', '4130', '4140', '4150', '4160', '9900'];

        $found = Account::where('company_id', $company->id)
            ->whereIn('code', $codes)
            ->where('is_active', true)
            ->pluck('id', 'code');

        return array_combine($codes, array_map(
            fn (string $code) => $found[$code] ?? null,
            $codes,
        ));
    }

    private function resolveDiscountReceivedAccountId(Company $company): ?string
    {
        $account = Account::where('company_id', $company->id)
            ->whereIn('type', ['other_income', 'revenue'])
            ->where(function ($q) {
                $q->where('name', 'Discounts Received')
                    ->orWhere('name', 'Purchase Discounts')
                    ->orWhere('code', '4300');
            })
            ->first(['id']);

        return $account?->id;
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
                'effective_from' => self::DEFAULT_EFFECTIVE_FROM,
                'effective_to' => null,
                'created_by_user_id' => $userId,
                'updated_by_user_id' => $userId,
            ]);
        } else {
            $this->backfillEffectiveFrom($template);
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

    private function backfillEffectiveFrom(PostingTemplate $template): void
    {
        if (! $template->is_default || ! $template->is_active) {
            return;
        }

        $description = strtolower((string) ($template->description ?? ''));
        if (! str_contains($description, 'auto-created')) {
            return;
        }

        if (! $template->effective_from instanceof Carbon) {
            return;
        }

        $minimum = Carbon::parse(self::DEFAULT_EFFECTIVE_FROM);
        if ($template->effective_from->greaterThan($minimum)) {
            $template->update(['effective_from' => self::DEFAULT_EFFECTIVE_FROM]);
        }
    }
}
