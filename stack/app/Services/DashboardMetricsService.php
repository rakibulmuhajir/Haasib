<?php

namespace App\Services;

use App\Models\Account;
use App\Models\Company;
use App\Models\Customer;
use App\Models\Invoice;
use App\Models\JournalEntry;
use App\Models\Payment;
use App\Traits\AuditLogging;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class DashboardMetricsService
{
    use AuditLogging;

    private ServiceContext $context;

    public function __construct(ServiceContext $context)
    {
        $this->context = $context;
    }

    /**
     * Get key dashboard metrics for a company
     */
    public function getCompanyMetrics(?Company $company = null): array
    {
        return DB::transaction(function () use ($company) {
            // Use company from context or validate provided company
            $targetCompany = $company ?? $this->context->getCompany();

            if (! $targetCompany) {
                throw new \InvalidArgumentException('Company context is required');
            }

            // Validate user can access this company
            $this->validateCompanyAccess($targetCompany);

            $companyId = $targetCompany->id;
            $currency = $targetCompany->currency_code ?? 'USD';

            // Log metrics calculation
            $this->audit('dashboard.metrics_generated', [
                'company_id' => $companyId,
                'generated_by_user_id' => $this->context->getUserId(),
                'currency' => $currency,
                'calculation_timestamp' => now()->toISOString(),
            ]);

            Log::info('Dashboard metrics generated', [
                'company_id' => $companyId,
                'user_id' => $this->context->getUserId(),
                'ip' => $this->context->getIpAddress(),
            ]);

            return [
                'cash_balance' => $this->getCashBalance($companyId, $currency),
                'outstanding_invoices' => $this->getOutstandingInvoices($companyId, $currency),
                'overdue_invoices' => $this->getOverdueInvoices($companyId, $currency),
                'total_customers' => $this->getTotalCustomers($companyId),
                'monthly_revenue' => $this->getMonthlyRevenue($companyId, $currency),
                'monthly_expenses' => $this->getMonthlyExpenses($companyId, $currency),
                'accounts_receivable' => $this->getAccountsReceivable($companyId, $currency),
                'accounts_payable' => $this->getAccountsPayable($companyId, $currency),
                'net_income' => $this->getNetIncome($companyId, $currency),
                'profit_margin' => $this->getProfitMargin($companyId),
                'collection_rate' => $this->getCollectionRate($companyId),
                'recent_activity' => $this->getRecentActivity($companyId),
                'top_customers' => $this->getTopCustomers($companyId, $currency),
                'generated_at' => now()->toISOString(),
                'company_id' => $companyId,
                'currency' => $currency,
            ];
        });
    }

    /**
     * Validate user can access the company
     */
    private function validateCompanyAccess(Company $company): void
    {
        $user = $this->context->getUser();

        if (! $user) {
            throw new \InvalidArgumentException('User context is required');
        }

        // Check if user belongs to this company
        if (! $user->companies()->where('company_id', $company->id)->exists()) {
            throw new \InvalidArgumentException('User does not have access to this company');
        }

        // Additional validation for active company membership
        $companyMembership = $user->companies()
            ->where('company_id', $company->id)
            ->wherePivot('is_active', true)
            ->first();

        if (! $companyMembership) {
            throw new \InvalidArgumentException('User access to this company is not active');
        }
    }

    /**
     * Set RLS context for database operations
     */
    private function setRlsContext(string $companyId): void
    {
        $escapedCompanyId = addslashes($companyId);
        DB::statement("SET app.current_company_id = '{$escapedCompanyId}'");
        DB::statement('SET app.current_user_id = ?', [$this->context->getUserId()]);
    }

    /**
     * Get current cash balance
     */
    private function getCashBalance(string $companyId, string $currency): float
    {
        // Set RLS context for this query
        $this->setRlsContext($companyId);

        $cashAccountIds = Account::where('company_id', $companyId)
            ->where('account_type', 'Asset')
            ->where(function ($query) {
                $query->where('account_name', 'ILIKE', '%cash%')
                    ->orWhere('account_name', 'ILIKE', '%bank%')
                    ->orWhere('account_name', 'ILIKE', '%checking%')
                    ->orWhere('account_name', 'ILIKE', '%savings%');
            })
            ->pluck('id');

        if ($cashAccountIds->isEmpty()) {
            return 0.0;
        }

        $balance = JournalEntry::join('journal_lines', 'journal_entries.id', '=', 'journal_lines.journal_entry_id')
            ->whereIn('journal_lines.account_id', $cashAccountIds)
            ->where('journal_entries.company_id', $companyId)
            ->where('journal_entries.post_date', '<=', now())
            ->sum(DB::raw('journal_lines.debit - journal_lines.credit'));

        // Log cash balance calculation
        $this->audit('dashboard.cash_balance_calculated', [
            'company_id' => $companyId,
            'amount' => $balance,
            'currency' => $currency,
            'calculated_by_user_id' => $this->context->getUserId(),
        ]);

        return $balance;
    }

    /**
     * Get total outstanding invoices
     */
    private function getOutstandingInvoices(string $companyId, string $currency): array
    {
        $invoices = Invoice::where('company_id', $companyId)
            ->where('status', '!=', 'paid')
            ->where('status', '!=', 'cancelled')
            ->get();

        $totalAmount = $invoices->sum('total_amount');
        $totalPaid = $invoices->sum('paid_amount');
        $outstanding = $totalAmount - $totalPaid;

        return [
            'count' => $invoices->count(),
            'total_amount' => $totalAmount,
            'amount_paid' => $totalPaid,
            'outstanding_amount' => $outstanding,
            'currency' => $currency,
            'average_days_outstanding' => $this->getAverageDaysOutstanding($invoices),
        ];
    }

    /**
     * Get overdue invoices
     */
    private function getOverdueInvoices(string $companyId, string $currency): array
    {
        $overdueInvoices = Invoice::where('company_id', $companyId)
            ->where('status', '!=', 'paid')
            ->where('status', '!=', 'cancelled')
            ->where('due_date', '<', now())
            ->get();

        $totalAmount = $overdueInvoices->sum('total_amount');
        $totalPaid = $overdueInvoices->sum('paid_amount');
        $overdue = $totalAmount - $totalPaid;

        return [
            'count' => $overdueInvoices->count(),
            'total_amount' => $totalAmount,
            'amount_paid' => $totalPaid,
            'overdue_amount' => $overdue,
            'currency' => $currency,
            'percentage_of_receivables' => $this->getOverduePercentage($companyId, $overdue),
        ];
    }

    /**
     * Get total customer count
     */
    private function getTotalCustomers(string $companyId): int
    {
        return Customer::where('company_id', $companyId)->count();
    }

    /**
     * Get monthly revenue
     */
    private function getMonthlyRevenue(string $companyId, string $currency): array
    {
        $startDate = now()->startOfMonth();
        $endDate = now()->endOfMonth();

        $monthlyInvoices = Invoice::where('company_id', $companyId)
            ->where('invoice_date', '>=', $startDate)
            ->where('invoice_date', '<=', $endDate)
            ->where('status', '!=', 'cancelled')
            ->get();

        $totalRevenue = $monthlyInvoices->sum('total_amount');
        $paidRevenue = Payment::where('company_id', $companyId)
            ->where('payment_date', '>=', $startDate)
            ->where('payment_date', '<=', $endDate)
            ->sum('amount');

        return [
            'total' => $totalRevenue,
            'paid' => $paidRevenue,
            'outstanding' => $totalRevenue - $paidRevenue,
            'invoice_count' => $monthlyInvoices->count(),
            'currency' => $currency,
            'growth_vs_last_month' => $this->getMonthlyRevenueGrowth($companyId, $startDate),
        ];
    }

    /**
     * Get monthly expenses
     */
    private function getMonthlyExpenses(string $companyId, string $currency): array
    {
        $startDate = now()->startOfMonth();
        $endDate = now()->endOfMonth();

        // Get expense accounts
        $expenseAccountIds = Account::where('company_id', $companyId)
            ->where('account_type', 'Expense')
            ->pluck('id');

        if ($expenseAccountIds->isEmpty()) {
            return [
                'total' => 0,
                'currency' => $currency,
                'growth_vs_last_month' => 0,
            ];
        }

        $monthlyExpenses = JournalEntry::join('journal_lines', 'journal_entries.id', '=', 'journal_lines.journal_entry_id')
            ->whereIn('journal_lines.account_id', $expenseAccountIds)
            ->where('journal_entries.company_id', $companyId)
            ->where('journal_entries.post_date', '>=', $startDate)
            ->where('journal_entries.post_date', '<=', $endDate)
            ->sum('journal_lines.debit');

        return [
            'total' => $monthlyExpenses,
            'currency' => $currency,
            'growth_vs_last_month' => $this->getMonthlyExpensesGrowth($companyId, $startDate, $expenseAccountIds),
        ];
    }

    /**
     * Get accounts receivable total
     */
    private function getAccountsReceivable(string $companyId, string $currency): float
    {
        $arAccountIds = Account::where('company_id', $companyId)
            ->where('account_type', 'Asset')
            ->where(function ($query) {
                $query->where('account_name', 'ILIKE', '%accounts receivable%')
                    ->orWhere('account_name', 'ILIKE', '%trade receivables%')
                    ->orWhere('account_name', 'ILIKE', '%a/r%');
            })
            ->pluck('id');

        if ($arAccountIds->isEmpty()) {
            // Fallback to invoice calculation
            return Invoice::where('company_id', $companyId)
                ->where('status', '!=', 'paid')
                ->where('status', '!=', 'cancelled')
                ->sum(DB::raw('total_amount - paid_amount'));
        }

        return JournalEntry::join('journal_lines', 'journal_entries.id', '=', 'journal_lines.journal_entry_id')
            ->whereIn('journal_lines.account_id', $arAccountIds)
            ->where('journal_entries.company_id', $companyId)
            ->sum(DB::raw('journal_lines.debit - journal_lines.credit'));
    }

    /**
     * Get accounts payable total
     */
    private function getAccountsPayable(string $companyId, string $currency): float
    {
        $apAccountIds = Account::where('company_id', $companyId)
            ->where('account_type', 'Liability')
            ->where(function ($query) {
                $query->where('account_name', 'ILIKE', '%accounts payable%')
                    ->orWhere('account_name', 'ILIKE', '%trade payables%')
                    ->orWhere('account_name', 'ILIKE', '%a/p%');
            })
            ->pluck('id');

        if ($apAccountIds->isEmpty()) {
            // Fallback to bill calculation if bills exist
            if (class_exists('App\Models\Bill')) {
                return \App\Models\Bill::where('company_id', $companyId)
                    ->where('status', '!=', 'paid')
                    ->where('status', '!=', 'cancelled')
                    ->sum(DB::raw('total_amount - amount_paid'));
            }

            return 0.0;
        }

        return JournalEntry::join('journal_lines', 'journal_entries.id', '=', 'journal_lines.journal_entry_id')
            ->whereIn('journal_lines.account_id', $apAccountIds)
            ->where('journal_entries.company_id', $companyId)
            ->sum(DB::raw('journal_lines.credit - journal_lines.debit'));
    }

    /**
     * Get net income (revenue - expenses)
     */
    private function getNetIncome(string $companyId, string $currency): array
    {
        $revenue = $this->getMonthlyRevenue($companyId, $currency);
        $expenses = $this->getMonthlyExpenses($companyId, $currency);

        $netIncome = $revenue['total'] - $expenses['total'];

        return [
            'amount' => $netIncome,
            'currency' => $currency,
            'revenue' => $revenue['total'],
            'expenses' => $expenses['total'],
            'profit_margin' => $revenue['total'] > 0 ? ($netIncome / $revenue['total']) * 100 : 0,
        ];
    }

    /**
     * Get profit margin percentage
     */
    private function getProfitMargin(string $companyId): float
    {
        $netIncome = $this->getNetIncome($companyId, 'USD');

        if ($netIncome['revenue'] <= 0) {
            return 0.0;
        }

        return round($netIncome['profit_margin'], 2);
    }

    /**
     * Get collection rate
     */
    private function getCollectionRate(string $companyId): float
    {
        $periodStart = now()->subDays(90);
        $periodEnd = now();

        $totalInvoiced = Invoice::where('company_id', $companyId)
            ->where('invoice_date', '>=', $periodStart)
            ->where('invoice_date', '<=', $periodEnd)
            ->where('status', '!=', 'cancelled')
            ->sum('total_amount');

        $totalCollected = Payment::where('company_id', $companyId)
            ->where('payment_date', '>=', $periodStart)
            ->where('payment_date', '<=', $periodEnd)
            ->sum('amount');

        if ($totalInvoiced <= 0) {
            return 0.0;
        }

        return round(($totalCollected / $totalInvoiced) * 100, 2);
    }

    /**
     * Get recent activity
     */
    private function getRecentActivity(string $companyId): array
    {
        $activity = [];

        // Recent invoices
        $recentInvoices = Invoice::where('company_id', $companyId)
            ->orderBy('created_at', 'desc')
            ->limit(3)
            ->get()
            ->map(function ($invoice) {
                return [
                    'type' => 'invoice',
                    'description' => "Invoice #{$invoice->invoice_number}",
                    'amount' => $invoice->total_amount,
                    'status' => $invoice->status,
                    'date' => $invoice->created_at->format('M j, Y'),
                ];
            });

        // Recent payments
        $recentPayments = Payment::where('company_id', $companyId)
            ->orderBy('created_at', 'desc')
            ->limit(3)
            ->get()
            ->map(function ($payment) {
                return [
                    'type' => 'payment',
                    'description' => "Payment #{$payment->payment_number}",
                    'amount' => $payment->amount,
                    'method' => $payment->payment_method ?? 'unknown',
                    'date' => $payment->created_at->format('M j, Y'),
                ];
            });

        $activity = $recentInvoices->concat($recentPayments)
            ->sortByDesc('date')
            ->take(5)
            ->values();

        return $activity->toArray();
    }

    /**
     * Get top customers by revenue
     */
    private function getTopCustomers(string $companyId, string $currency): array
    {
        return Customer::where('company_id', $companyId)
            ->withSum('invoices as total_revenue', 'total_amount')
            ->orderByDesc('total_revenue')
            ->limit(5)
            ->get()
            ->map(function ($customer) use ($currency) {
                return [
                    'name' => $customer->name,
                    'revenue' => $customer->total_revenue,
                    'invoice_count' => $customer->invoices()->count(),
                    'currency' => $currency,
                ];
            })
            ->toArray();
    }

    // Helper methods for calculations
    private function getAverageDaysOutstanding($invoices): float
    {
        if ($invoices->isEmpty()) {
            return 0.0;
        }

        $totalDays = 0;
        $count = 0;

        foreach ($invoices as $invoice) {
            $days = now()->diffInDays($invoice->due_date);
            $totalDays += max(0, $days); // Don't count negative days
            $count++;
        }

        return $count > 0 ? round($totalDays / $count, 1) : 0.0;
    }

    private function getOverduePercentage(string $companyId, float $overdueAmount): float
    {
        $totalReceivables = $this->getAccountsReceivable($companyId, 'USD');

        if ($totalReceivables <= 0) {
            return 0.0;
        }

        return round(($overdueAmount / $totalReceivables) * 100, 2);
    }

    private function getMonthlyRevenueGrowth(string $companyId, Carbon $currentMonthStart): float
    {
        $previousMonthStart = $currentMonthStart->copy()->subMonth();
        $previousMonthEnd = $currentMonthStart->copy()->subDay();

        $currentRevenue = Invoice::where('company_id', $companyId)
            ->where('invoice_date', '>=', $currentMonthStart)
            ->where('invoice_date', '<=', now())
            ->where('status', '!=', 'cancelled')
            ->sum('total_amount');

        $previousRevenue = Invoice::where('company_id', $companyId)
            ->where('invoice_date', '>=', $previousMonthStart)
            ->where('invoice_date', '<=', $previousMonthEnd)
            ->where('status', '!=', 'cancelled')
            ->sum('total_amount');

        if ($previousRevenue <= 0) {
            return $currentRevenue > 0 ? 100.0 : 0.0;
        }

        return round((($currentRevenue - $previousRevenue) / $previousRevenue) * 100, 2);
    }

    private function getMonthlyExpensesGrowth(string $companyId, Carbon $currentMonthStart, $expenseAccountIds): float
    {
        $previousMonthStart = $currentMonthStart->copy()->subMonth();
        $previousMonthEnd = $currentMonthStart->copy()->subDay();

        $currentExpenses = JournalEntry::join('journal_lines', 'journal_entries.id', '=', 'journal_lines.journal_entry_id')
            ->whereIn('journal_lines.account_id', $expenseAccountIds)
            ->where('journal_entries.company_id', $companyId)
            ->where('journal_entries.post_date', '>=', $currentMonthStart)
            ->where('journal_entries.post_date', '<=', now())
            ->sum('journal_lines.debit');

        $previousExpenses = JournalEntry::join('journal_lines', 'journal_entries.id', '=', 'journal_lines.journal_entry_id')
            ->whereIn('journal_lines.account_id', $expenseAccountIds)
            ->where('journal_entries.company_id', $companyId)
            ->where('journal_entries.post_date', '>=', $previousMonthStart)
            ->where('journal_entries.post_date', '<=', $previousMonthEnd)
            ->sum('journal_lines.debit');

        if ($previousExpenses <= 0) {
            return $currentExpenses > 0 ? 100.0 : 0.0;
        }

        return round((($currentExpenses - $previousExpenses) / $previousExpenses) * 100, 2);
    }
}
