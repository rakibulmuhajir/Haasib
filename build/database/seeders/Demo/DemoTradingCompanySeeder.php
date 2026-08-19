<?php

namespace Database\Seeders\Demo;

use App\Models\Company;
use App\Modules\Accounting\Models\Bill;
use App\Modules\Accounting\Models\BillLineItem;
use App\Modules\Accounting\Models\Customer;
use App\Modules\Accounting\Models\Invoice;
use App\Modules\Accounting\Models\InvoiceLineItem;
use Illuminate\Support\Facades\DB;
use App\Modules\Accounting\Models\Payment;
use App\Modules\Accounting\Models\PaymentAllocation;
use App\Modules\Accounting\Models\Vendor;
use App\Modules\Accounting\Services\GlPostingService;
use Carbon\Carbon;
use Illuminate\Database\Seeder;

/**
 * Meridian Trading Co. — the general-accounting demo company.
 *
 * Produces the screenshots that carry the most weight: a general ledger and a
 * trial balance with real rows, an AR/AP aging with real balances, and a P&L
 * with a real bottom line. Every figure here is posted through GlPostingService,
 * so the ledger balances because the posting engine made it balance.
 */
class DemoTradingCompanySeeder extends Seeder
{
    use DemoSupport;

    public const SLUG = 'demo-meridian-trading';

    public function run(): void
    {
        $this->purgeDemoCompany(self::SLUG);

        $user = $this->demoUser();

        $company = $this->buildCompany(
            user: $user,
            name: 'Meridian Trading Co.',
            slug: self::SLUG,
            industryCode: 'wholesale',
            currency: 'PKR',
            country: 'PK',
            bankAccounts: [
                ['account_name' => 'Meezan Bank — Current', 'account_type' => 'bank', 'currency' => 'PKR'],
                ['account_name' => 'Petty Cash', 'account_type' => 'cash', 'currency' => 'PKR'],
            ],
            tradeName: 'Meridian Trading',
            industry: 'retail',
            address: [
                'line1' => 'Suite 302, Business Avenue',
                'line2' => 'Shahrah-e-Faisal',
                'city' => 'Karachi',
                'state' => 'Sindh',
                'postal_code' => '75350',
                'country' => 'Pakistan',
            ],
            contact: [
                'contact_email' => 'billing@meridiantrading.pk',
                'contact_phone' => '+92 21 3438 9100',
            ],
            taxNumber: '1728934-5',
        );

        $this->command?->info("  company: {$company->name} ({$company->id})");

        $bank = $this->accountBySubtype($company, 'bank');
        $cash = $this->accountBySubtype($company, 'cash');
        $ar = $this->accountBySubtype($company, 'accounts_receivable');
        $ap = $this->accountBySubtype($company, 'accounts_payable');
        $retained = $this->accountBySubtype($company, 'retained_earnings');

        // Accounts the `wholesale` chart-of-accounts pack ships with. Use the pack's
        // own codes so the demo looks exactly like a real onboarded wholesale company.
        $sales = $this->requireAccount($company, '4100', 'Wholesale Sales');
        $distributionFees = $this->requireAccount($company, '4110', 'Distribution Fees');
        $cogs = $this->requireAccount($company, '5100', 'Cost of Goods Sold');
        $inventory = $this->requireAccount($company, '1300', 'Bulk Inventory');
        $admin = $this->requireAccount($company, '6100', 'General & Administrative');
        $rent = $this->requireAccount($company, '6300', 'Warehouse Rent');
        $freight = $this->requireAccount($company, '6350', 'Logistics Costs');

        // The pack doesn't ship these three; a real trading company would add them
        // during setup, so the demo adds them the same way.
        $capital = $this->ensureAccount($company, '3000', 'Share Capital', 'equity', 'equity', 'credit');
        $salaries = $this->ensureAccount($company, '6200', 'Salaries & Wages', 'expense', 'operating_expense', 'debit');
        $bankCharges = $this->ensureAccount($company, '6500', 'Bank Charges', 'expense', 'operating_expense', 'debit');

        $gl = app(GlPostingService::class);
        $year = now()->year;

        // ---- Opening balances -------------------------------------------------
        // Capital injected, opening stock on hand, opening cash. One balanced entry.
        $gl->postBalancedTransaction([
            'company_id' => $company->id,
            'transaction_type' => 'journal',
            'date' => Carbon::create($year, 1, 1),
            'currency' => 'PKR',
            'description' => 'Opening balances',
        ], [
            ['account_id' => $bank->id, 'type' => 'debit', 'amount' => 4_500_000, 'description' => 'Opening bank balance'],
            ['account_id' => $cash->id, 'type' => 'debit', 'amount' => 120_000, 'description' => 'Opening petty cash'],
            ['account_id' => $inventory->id, 'type' => 'debit', 'amount' => 2_800_000, 'description' => 'Opening stock at cost'],
            ['account_id' => $capital->id, 'type' => 'credit', 'amount' => 6_000_000, 'description' => 'Share capital'],
            ['account_id' => $retained->id, 'type' => 'credit', 'amount' => 1_420_000, 'description' => 'Retained earnings brought forward'],
        ]);

        // ---- Customers and vendors -------------------------------------------
        $customers = collect([
            ['Al-Karam Distributors', 'accounts@alkaram-dist.example', '+92 21 3456 7801', 30],
            ['Gulberg Retail Group', 'finance@gulbergretail.example', '+92 42 3567 8902', 30],
            ['Sarhad Wholesale', 'ap@sarhadwholesale.example', '+92 91 5678 9013', 15],
            ['Indus Supply Chain', 'billing@indussupply.example', '+92 21 3789 0124', 45],
            ['Northline Traders', 'accounts@northline.example', '+92 51 2890 1235', 30],
        ])->map(fn ($c, $i) => Customer::create([
            'company_id' => $company->id,
            'customer_number' => 'CUST-'.str_pad((string) ($i + 1), 4, '0', STR_PAD_LEFT),
            'name' => $c[0],
            'email' => $c[1],
            'phone' => $c[2],
            'billing_address' => 'Karachi, Pakistan',
            'base_currency' => 'PKR',
            'payment_terms' => $c[3],
            'credit_limit' => 2_000_000,
            'ar_account_id' => $ar->id,
            'is_active' => true,
        ]));

        $vendors = collect([
            ['Crescent Mills Ltd', 'sales@crescentmills.example', 30, 'general'],
            ['Ravi Packaging', 'orders@ravipack.example', 15, 'general'],
            ['Shaheen Logistics', 'billing@shaheenlog.example', 30, 'service_provider'],
            ['Northgrid Power', 'commercial@northgrid.example', 7, 'utility'],
        ])->map(fn ($v, $i) => Vendor::create([
            'company_id' => $company->id,
            'vendor_number' => 'VEND-'.str_pad((string) ($i + 1), 4, '0', STR_PAD_LEFT),
            'name' => $v[0],
            'email' => $v[1],
            'vendor_type' => $v[3],
            'address' => 'Lahore, Pakistan',
            'base_currency' => 'PKR',
            'payment_terms' => $v[2],
            'ap_account_id' => $ap->id,
            'is_active' => true,
        ]));

        $this->command?->info("  customers: {$customers->count()}  vendors: {$vendors->count()}");

        // ---- Sales invoices ---------------------------------------------------
        // Spread across the year, mixed statuses, so AR aging has something to show.
        // Trailing flag: 'goods' bills to Wholesale Sales and carries COGS;
        // 'delivery' bills to Distribution Fees and carries none.
        $invoiceSpec = [
            [1, 12, 0, 485_000, 'paid', 'goods'],
            [2, 3, 0, 1_240_000, 'paid', 'goods'],
            [2, 19, 1, 675_500, 'paid', 'goods'],
            [3, 8, 2, 312_000, 'paid', 'delivery'],
            [3, 27, 3, 1_890_000, 'partial', 'goods'],
            [4, 14, 4, 540_750, 'paid', 'goods'],
            [5, 2, 0, 928_000, 'paid', 'goods'],
            [5, 21, 1, 1_115_000, 'partial', 'goods'],
            [6, 9, 2, 268_400, 'sent', 'delivery'],
            [6, 25, 3, 1_450_000, 'sent', 'goods'],
            [7, 11, 4, 702_300, 'sent', 'goods'],
            [7, 30, 0, 385_900, 'sent', 'goods'],
        ];

        $invoiceNo = 1;
        $paymentNo = 1;

        // `deposit_account_id` names the GL account the money landed in, not the
        // company_bank_accounts row -- the FK points at acct.accounts.
        $depositAccountId = $bank->id;

        foreach ($invoiceSpec as [$month, $day, $custIdx, $amount, $status, $kind]) {
            $date = Carbon::create($year, $month, $day);
            if ($date->isFuture()) {
                continue;
            }

            $customer = $customers[$custIdx];
            $terms = (int) $customer->payment_terms;
            $paid = match ($status) {
                'paid' => $amount,
                'partial' => round($amount * 0.4, 2),
                default => 0.0,
            };

            $invoice = Invoice::create([
                'company_id' => $company->id,
                'customer_id' => $customer->id,
                'invoice_number' => sprintf('INV-%d-%04d', $year, $invoiceNo),
                'invoice_date' => $date,
                'due_date' => $date->copy()->addDays($terms),
                'status' => $status,
                'currency' => 'PKR',
                'base_currency' => 'PKR',
                'exchange_rate' => 1,
                'subtotal' => $amount,
                'tax_amount' => 0,
                'discount_amount' => 0,
                'total_amount' => $amount,
                'paid_amount' => $paid,
                'balance' => $amount - $paid,
                'base_amount' => $amount,
                'payment_terms' => $terms,
                'paid_at' => $status === 'paid' ? $date->copy()->addDays((int) round($terms * 0.7)) : null,
                'sent_at' => $date,
            ]);

            InvoiceLineItem::create([
                'company_id' => $company->id,
                'invoice_id' => $invoice->id,
                'line_number' => 1,
                'description' => $kind === 'goods'
                    ? 'Bulk goods supply — order '.sprintf('SO-%d-%04d', $year, $invoiceNo)
                    : 'Distribution and last-mile delivery — '.$date->format('F Y'),
                'quantity' => 1,
                'unit_price' => $amount,
                'tax_rate' => 0,
                'discount_rate' => 0,
                'line_total' => $amount,
                'tax_amount' => 0,
                'total' => $amount,
                'income_account_id' => $kind === 'goods' ? $sales->id : $distributionFees->id,
            ]);

            $gl->postInvoice($invoice->fresh(['lineItems', 'customer', 'company']));

            // Cost of the goods sold on that invoice — roughly a 32% gross margin.
            // Delivery work carries no inventory cost.
            if ($kind === 'goods') {
                $cost = round($amount * 0.68, 2);
                $gl->postBalancedTransaction([
                    'company_id' => $company->id,
                    'transaction_type' => 'journal',
                    'date' => $date,
                    'currency' => 'PKR',
                    'description' => "COGS for {$invoice->invoice_number}",
                ], [
                    ['account_id' => $cogs->id, 'type' => 'debit', 'amount' => $cost],
                    ['account_id' => $inventory->id, 'type' => 'credit', 'amount' => $cost],
                ]);
            }

            // Cash received against the invoice.
            //
            // Recorded as a real Payment allocated to the invoice, not only as a
            // GL receipt. The two used to be the same posting, which balanced the
            // books but left the Payments screen empty -- a page that has never
            // been seen with data in it is a page nobody has verified.
            if ($paid > 0) {
                $receiptDate = $date->copy()->addDays((int) round($terms * 0.7));
                if ($receiptDate->isFuture()) {
                    $receiptDate = Carbon::now()->subDay();
                }

                // The stored vocabulary is 'check'; the UI shows it as "Cheque".
                $method = ['bank_transfer', 'check', 'cash'][$invoiceNo % 3];

                $receipt = $gl->postBalancedTransaction([
                    'company_id' => $company->id,
                    'transaction_type' => 'receipt',
                    'date' => $receiptDate,
                    'currency' => 'PKR',
                    'description' => "Receipt from {$customer->name} — {$invoice->invoice_number}",
                ], [
                    ['account_id' => $bank->id, 'type' => 'debit', 'amount' => $paid],
                    ['account_id' => $ar->id, 'type' => 'credit', 'amount' => $paid],
                ]);

                $payment = Payment::create([
                    'company_id' => $company->id,
                    'customer_id' => $customer->id,
                    'payment_number' => sprintf('PAY-%d-%04d', $year, $paymentNo),
                    'payment_date' => $receiptDate,
                    'amount' => $paid,
                    'currency' => 'PKR',
                    'exchange_rate' => 1,
                    'base_currency' => 'PKR',
                    'base_amount' => $paid,
                    'payment_method' => $method,
                    'deposit_account_id' => $depositAccountId,
                    'transaction_id' => $receipt->id,
                    'reference_number' => $method === 'check'
                        ? sprintf('CHQ-%06d', 400000 + $paymentNo * 137)
                        : null,
                    'notes' => "Against {$invoice->invoice_number}",
                    'created_by_user_id' => $user->id,
                ]);

                PaymentAllocation::create([
                    'company_id' => $company->id,
                    'payment_id' => $payment->id,
                    'invoice_id' => $invoice->id,
                    'amount_allocated' => $paid,
                    'base_amount_allocated' => $paid,
                    'applied_at' => $receiptDate,
                ]);

                $paymentNo++;
            }

            $invoiceNo++;
        }

        // ---- Purchase bills ---------------------------------------------------
        $billSpec = [
            [1, 18, 0, 1_650_000, true],
            [2, 14, 1, 240_000, true],
            [3, 12, 2, 185_000, true],
            [4, 5, 0, 2_100_000, true],
            [5, 16, 3, 96_500, true],
            [6, 20, 1, 310_000, false],
            [7, 15, 0, 1_380_000, false],
        ];

        $billNo = 1;
        foreach ($billSpec as [$month, $day, $vendIdx, $amount, $settled]) {
            $date = Carbon::create($year, $month, $day);
            if ($date->isFuture()) {
                continue;
            }

            $vendor = $vendors[$vendIdx];
            $terms = (int) $vendor->payment_terms;
            $expenseAccount = match ($vendIdx) {
                0 => $inventory,
                1 => $inventory,
                2 => $freight,
                default => $admin,
            };

            $bill = Bill::create([
                'company_id' => $company->id,
                'vendor_id' => $vendor->id,
                'bill_number' => sprintf('BILL-%d-%04d', $year, $billNo),
                'vendor_invoice_number' => sprintf('%s-%05d', strtoupper(substr($vendor->name, 0, 3)), 1000 + $billNo),
                'bill_date' => $date,
                'due_date' => $date->copy()->addDays($terms),
                'status' => $settled ? 'paid' : 'approved',
                'currency' => 'PKR',
                'base_currency' => 'PKR',
                'exchange_rate' => 1,
                'subtotal' => $amount,
                'tax_amount' => 0,
                'discount_amount' => 0,
                'total_amount' => $amount,
                'paid_amount' => $settled ? $amount : 0,
                'balance' => $settled ? 0 : $amount,
                'base_amount' => $amount,
                'payment_terms' => $terms,
                'received_at' => $date,
                'approved_at' => $date,
                'paid_at' => $settled ? $date->copy()->addDays($terms) : null,
            ]);

            BillLineItem::create([
                'company_id' => $company->id,
                'bill_id' => $bill->id,
                'line_number' => 1,
                'description' => $vendIdx <= 1 ? 'Goods for resale' : 'Services rendered',
                'quantity' => 1,
                'unit_price' => $amount,
                'tax_rate' => 0,
                'discount_rate' => 0,
                'line_total' => $amount,
                'tax_amount' => 0,
                'total' => $amount,
                'expense_account_id' => $expenseAccount->id,
                'account_id' => $expenseAccount->id,
            ]);

            $gl->postBill($bill->fresh(['lineItems', 'vendor', 'company']));

            if ($settled) {
                $payDate = $date->copy()->addDays($terms);
                if ($payDate->isFuture()) {
                    $payDate = Carbon::now()->subDay();
                }
                $gl->postBalancedTransaction([
                    'company_id' => $company->id,
                    'transaction_type' => 'payment',
                    'date' => $payDate,
                    'currency' => 'PKR',
                    'description' => "Payment to {$vendor->name} — {$bill->bill_number}",
                ], [
                    ['account_id' => $ap->id, 'type' => 'debit', 'amount' => $amount],
                    ['account_id' => $bank->id, 'type' => 'credit', 'amount' => $amount],
                ]);
            }

            $billNo++;
        }

        // ---- Recurring monthly overheads --------------------------------------
        for ($month = 1; $month <= 12; $month++) {
            $date = Carbon::create($year, $month, 28);
            if ($date->isFuture()) {
                break;
            }

            $gl->postBalancedTransaction([
                'company_id' => $company->id,
                'transaction_type' => 'journal',
                'date' => $date,
                'currency' => 'PKR',
                'description' => 'Monthly overheads — '.$date->format('F Y'),
            ], [
                // Sized against ~1.4M/month of revenue at a 36% gross margin, so the
                // demo lands on a believable ~10% net margin rather than a loss.
                ['account_id' => $rent->id, 'type' => 'debit', 'amount' => 95_000, 'description' => 'Warehouse and office rent'],
                ['account_id' => $salaries->id, 'type' => 'debit', 'amount' => 195_000, 'description' => 'Staff salaries'],
                ['account_id' => $admin->id, 'type' => 'debit', 'amount' => 34_500, 'description' => 'Electricity, internet and office costs'],
                ['account_id' => $bankCharges->id, 'type' => 'debit', 'amount' => 3_200, 'description' => 'Bank service charges'],
                ['account_id' => $bank->id, 'type' => 'credit', 'amount' => 327_700],
            ]);
        }

        $this->command?->info('  posted opening balances, invoices, bills, receipts, payments and overheads');

        $this->syncBankBalances($company);
    }
}
