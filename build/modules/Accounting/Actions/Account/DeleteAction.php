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

        $hasChildren = Account::where('company_id', $company->id)->where('parent_id', $account->id)->exists();
        if ($hasChildren) {
            throw new \InvalidArgumentException('Cannot delete account with children');
        }

        $hasPostedLines = DB::table('acct.journal_entries')
            ->where('company_id', $company->id)
            ->where('account_id', $account->id)
            ->exists();
        if ($hasPostedLines) {
            throw new \InvalidArgumentException('Cannot delete account with posted journal entries');
        }

        $usedInInvoices = DB::table('acct.invoice_line_items')
            ->where('company_id', $company->id)
            ->where('income_account_id', $account->id)
            ->exists();
        if ($usedInInvoices) {
            throw new \InvalidArgumentException('Cannot delete account used in invoice line items');
        }

        $usedInBills = DB::table('acct.bill_line_items')
            ->where('company_id', $company->id)
            ->where('expense_account_id', $account->id)
            ->exists();
        if ($usedInBills) {
            throw new \InvalidArgumentException('Cannot delete account used in bill line items');
        }

        $usedInVendorCredits = DB::table('acct.vendor_credit_items')
            ->where('company_id', $company->id)
            ->where('expense_account_id', $account->id)
            ->exists();
        if ($usedInVendorCredits) {
            throw new \InvalidArgumentException('Cannot delete account used in vendor credit items');
        }

        $usedInCustomers = DB::table('acct.customers')
            ->where('company_id', $company->id)
            ->where('ar_account_id', $account->id)
            ->exists();
        if ($usedInCustomers) {
            throw new \InvalidArgumentException('Cannot delete account used as a customer AR account');
        }

        $usedInVendors = DB::table('acct.vendors')
            ->where('company_id', $company->id)
            ->where('ap_account_id', $account->id)
            ->exists();
        if ($usedInVendors) {
            throw new \InvalidArgumentException('Cannot delete account used as a vendor AP account');
        }

        $usedInPayments = DB::table('acct.payments')
            ->where('company_id', $company->id)
            ->where('deposit_account_id', $account->id)
            ->exists();
        if ($usedInPayments) {
            throw new \InvalidArgumentException('Cannot delete account used as a payment deposit account');
        }

        $usedInBillPayments = DB::table('acct.bill_payments')
            ->where('company_id', $company->id)
            ->where('payment_account_id', $account->id)
            ->exists();
        if ($usedInBillPayments) {
            throw new \InvalidArgumentException('Cannot delete account used as a bill payment account');
        }

        $usedInPostingTemplates = DB::table('acct.posting_template_lines as l')
            ->join('acct.posting_templates as t', 't.id', '=', 'l.template_id')
            ->where('t.company_id', $company->id)
            ->whereNull('t.deleted_at')
            ->where('l.account_id', $account->id)
            ->exists();
        if ($usedInPostingTemplates) {
            throw new \InvalidArgumentException('Cannot delete account used in posting templates');
        }

        $usedInBankAccounts = DB::table('acct.company_bank_accounts')
            ->where('company_id', $company->id)
            ->where('gl_account_id', $account->id)
            ->whereNull('deleted_at')
            ->exists();
        if ($usedInBankAccounts) {
            throw new \InvalidArgumentException('Cannot delete account linked to a bank account');
        }

        $usedInCompanyDefaults = DB::table('auth.companies')
            ->where('id', $company->id)
            ->where(function ($q) use ($account) {
                $q->where('ar_account_id', $account->id)
                    ->orWhere('ap_account_id', $account->id)
                    ->orWhere('income_account_id', $account->id)
                    ->orWhere('expense_account_id', $account->id)
                    ->orWhere('bank_account_id', $account->id)
                    ->orWhere('retained_earnings_account_id', $account->id)
                    ->orWhere('sales_tax_payable_account_id', $account->id)
                    ->orWhere('purchase_tax_receivable_account_id', $account->id);
            })
            ->exists();
        if ($usedInCompanyDefaults) {
            throw new \InvalidArgumentException('Cannot delete account used as a company default account');
        }

        $account->delete();

        return [
            'message' => "Account {$account->code} deleted",
        ];
    }
}
