<?php

namespace Modules\Accounting\Domain\Customers\Services;

use App\Models\CreditNote;
use App\Models\Invoice;
use App\Models\Payment;
use Carbon\Carbon;
use Dompdf\Dompdf;
use Dompdf\Options;
use Illuminate\Support\Facades\Storage;
use Modules\Accounting\Domain\Customers\Models\Customer;
use Modules\Accounting\Domain\Customers\Models\CustomerStatement;

class CustomerStatementService
{
    /**
     * Generate statement data for a customer for a specific period.
     */
    public function generateStatementData(Customer $customer, Carbon $periodStart, Carbon $periodEnd): array
    {
        // Calculate opening balance (balance at start of period)
        $openingBalance = $this->calculateOpeningBalance($customer, $periodStart);

        // Get invoices within period
        $periodInvoices = $this->getPeriodInvoices($customer, $periodStart, $periodEnd);

        // Get payments within period
        $periodPayments = $this->getPeriodPayments($customer, $periodStart, $periodEnd);

        // Get credit notes within period
        $periodCreditNotes = $this->getPeriodCreditNotes($customer, $periodStart, $periodEnd);

        // Calculate totals
        $totalInvoiced = $periodInvoices->sum('total');
        $totalPaid = $periodPayments->sum('amount');
        $totalCreditNotes = $periodCreditNotes->sum('total');

        // Calculate closing balance
        $closingBalance = $openingBalance + $totalInvoiced - $totalPaid - $totalCreditNotes;

        // Get aging data as of period end
        $agingService = app(CustomerAgingService::class);
        $agingBuckets = $agingService->calculateAgingBuckets($customer, $periodEnd);

        return [
            'customer' => $customer,
            'period_start' => $periodStart,
            'period_end' => $periodEnd,
            'opening_balance' => $openingBalance,
            'total_invoiced' => $totalInvoiced,
            'total_paid' => $totalPaid,
            'total_credit_notes' => $totalCreditNotes,
            'closing_balance' => $closingBalance,
            'invoices' => $periodInvoices,
            'payments' => $periodPayments,
            'credit_notes' => $periodCreditNotes,
            'aging_buckets' => $agingBuckets,
            'currency' => $customer->default_currency,
            'generated_at' => now(),
        ];
    }

    /**
     * Calculate opening balance as of period start.
     */
    private function calculateOpeningBalance(Customer $customer, Carbon $periodStart): float
    {
        $totalInvoiced = Invoice::where('customer_id', $customer->id)
            ->where('company_id', $customer->company_id)
            ->where('issue_date', '<', $periodStart)
            ->where('status', '!=', 'draft')
            ->sum('total');

        $totalPaid = Payment::where('customer_id', $customer->id)
            ->where('company_id', $customer->company_id)
            ->where('payment_date', '<', $periodStart)
            ->where('status', 'completed')
            ->sum('amount');

        $totalCreditNotes = CreditNote::where('customer_id', $customer->id)
            ->where('company_id', $customer->company_id)
            ->where('issue_date', '<', $periodStart)
            ->where('status', '!=', 'draft')
            ->sum('total');

        return $totalInvoiced - $totalPaid - $totalCreditNotes;
    }

    /**
     * Get invoices within the specified period.
     */
    private function getPeriodInvoices(Customer $customer, Carbon $periodStart, Carbon $periodEnd): \Illuminate\Database\Eloquent\Collection
    {
        return Invoice::where('customer_id', $customer->id)
            ->where('company_id', $customer->company_id)
            ->where('issue_date', '>=', $periodStart->startOfDay())
            ->where('issue_date', '<=', $periodEnd->endOfDay())
            ->where('status', '!=', 'draft')
            ->orderBy('issue_date')
            ->orderBy('invoice_number')
            ->get();
    }

    /**
     * Get payments within the specified period.
     */
    private function getPeriodPayments(Customer $customer, Carbon $periodStart, Carbon $periodEnd): \Illuminate\Database\Eloquent\Collection
    {
        return Payment::where('customer_id', $customer->id)
            ->where('company_id', $customer->company_id)
            ->where('payment_date', '>=', $periodStart->startOfDay())
            ->where('payment_date', '<=', $periodEnd->endOfDay())
            ->where('status', 'completed')
            ->orderBy('payment_date')
            ->orderBy('payment_number')
            ->get();
    }

    /**
     * Get credit notes within the specified period.
     */
    private function getPeriodCreditNotes(Customer $customer, Carbon $periodStart, Carbon $periodEnd): \Illuminate\Database\Eloquent\Collection
    {
        return CreditNote::where('customer_id', $customer->id)
            ->where('company_id', $customer->company_id)
            ->where('issue_date', '>=', $periodStart->startOfDay())
            ->where('issue_date', '<=', $periodEnd->endOfDay())
            ->where('status', '!=', 'draft')
            ->orderBy('issue_date')
            ->orderBy('credit_note_number')
            ->get();
    }

    /**
     * Generate PDF document for statement.
     */
    public function generatePDFDocument(array $statementData): string
    {
        $options = new Options;
        $options->set('defaultFont', 'Arial');
        $options->set('isRemoteEnabled', false);
        $options->set('isHtml5ParserEnabled', true);

        $dompdf = new Dompdf($options);

        $html = $this->generateStatementHTML($statementData);
        $dompdf->loadHtml($html);
        $dompdf->setPaper('A4', 'portrait');
        $dompdf->render();

        $filename = "statements/{$statementData['customer']->id}/statement-{$statementData['period_start']->format('Y-m-d')}-to-{$statementData['period_end']->format('Y-m-d')}.pdf";

        Storage::put($filename, $dompdf->output());

        return $filename;
    }

    /**
     * Generate CSV document for statement.
     */
    public function generateCSVDocument(array $statementData): string
    {
        $filename = "statements/{$statementData['customer']->id}/statement-{$statementData['period_start']->format('Y-m-d')}-to-{$statementData['period_end']->format('Y-m-d')}.csv";

        $csv = $this->generateStatementCSV($statementData);

        Storage::put($filename, $csv);

        return $filename;
    }

    /**
     * Generate HTML content for PDF statement.
     */
    private function generateStatementHTML(array $statementData): string
    {
        $customer = $statementData['customer'];
        $periodStart = $statementData['period_start'];
        $periodEnd = $statementData['period_end'];

        return "
        <html>
        <head>
            <style>
                body { font-family: Arial, sans-serif; font-size: 12px; margin: 20px; }
                .header { text-align: center; margin-bottom: 30px; }
                .company-info { margin-bottom: 20px; }
                .customer-info { margin-bottom: 20px; }
                .period-info { margin-bottom: 30px; }
                .summary { margin-bottom: 30px; }
                table { width: 100%; border-collapse: collapse; margin-bottom: 20px; }
                th, td { border: 1px solid #ddd; padding: 8px; text-align: left; }
                th { background-color: #f2f2f2; font-weight: bold; }
                .text-right { text-align: right; }
                .text-center { text-align: center; }
                .total-row { font-weight: bold; background-color: #f9f9f9; }
                .aging-summary { margin-top: 30px; }
            </style>
        </head>
        <body>
            <div class='header'>
                <h2>Customer Statement</h2>
            </div>
            
            <div class='company-info'>
                <strong>Your Company Name</strong><br>
                Your Address<br>
                City, State ZIP<br>
                Phone: (555) 123-4567<br>
                Email: billing@yourcompany.com
            </div>
            
            <div class='customer-info'>
                <strong>Bill To:</strong><br>
                {$customer->name}<br>
                ".($customer->legal_name ? $customer->legal_name.'<br>' : '').'
                '.($customer->email ? $customer->email.'<br>' : '').'
                '.($customer->phone ? $customer->phone.'<br>' : '')."
            </div>
            
            <div class='period-info'>
                <strong>Statement Period:</strong> {$periodStart->format('M j, Y')} to {$periodEnd->format('M j, Y')}<br>
                <strong>Generated Date:</strong> ".now()->format('M j, Y H:i')."
            </div>
            
            <div class='summary'>
                <h3>Summary</h3>
                <table>
                    <tr>
                        <td><strong>Opening Balance</strong></td>
                        <td class='text-right'>".number_format($statementData['opening_balance'], 2)." {$statementData['currency']}</td>
                    </tr>
                    <tr>
                        <td><strong>Total Invoiced</strong></td>
                        <td class='text-right'>".number_format($statementData['total_invoiced'], 2)." {$statementData['currency']}</td>
                    </tr>
                    <tr>
                        <td><strong>Total Payments</strong></td>
                        <td class='text-right'>".number_format($statementData['total_paid'], 2)." {$statementData['currency']}</td>
                    </tr>
                    <tr>
                        <td><strong>Total Credit Notes</strong></td>
                        <td class='text-right'>".number_format($statementData['total_credit_notes'], 2)." {$statementData['currency']}</td>
                    </tr>
                    <tr class='total-row'>
                        <td><strong>Closing Balance</strong></td>
                        <td class='text-right'>".number_format($statementData['closing_balance'], 2)." {$statementData['currency']}</td>
                    </tr>
                </table>
            </div>";

        if ($statementData['invoices']->isNotEmpty()) {
            $html .= "
            <div>
                <h3>Invoices</h3>
                <table>
                    <thead>
                        <tr>
                            <th>Date</th>
                            <th>Invoice #</th>
                            <th>Description</th>
                            <th class='text-right'>Amount</th>
                        </tr>
                    </thead>
                    <tbody>";

            foreach ($statementData['invoices'] as $invoice) {
                $html .= '
                        <tr>
                            <td>'.$invoice->issue_date->format('M j, Y').'</td>
                            <td>'.$invoice->invoice_number.'</td>
                            <td>'.htmlspecialchars($invoice->description ?? 'Invoice')."</td>
                            <td class='text-right'>".number_format($invoice->total, 2).'</td>
                        </tr>';
            }

            $html .= "
                        <tr class='total-row'>
                            <td colspan='3'><strong>Total Invoices</strong></td>
                            <td class='text-right'><strong>".number_format($statementData['total_invoiced'], 2).'</strong></td>
                        </tr>
                    </tbody>
                </table>
            </div>';
        }

        if ($statementData['payments']->isNotEmpty()) {
            $html .= "
            <div>
                <h3>Payments</h3>
                <table>
                    <thead>
                        <tr>
                            <th>Date</th>
                            <th>Payment #</th>
                            <th>Method</th>
                            <th class='text-right'>Amount</th>
                        </tr>
                    </thead>
                    <tbody>";

            foreach ($statementData['payments'] as $payment) {
                $html .= '
                        <tr>
                            <td>'.$payment->payment_date->format('M j, Y').'</td>
                            <td>'.$payment->payment_number.'</td>
                            <td>'.ucfirst($payment->payment_method)."</td>
                            <td class='text-right'>".number_format($payment->amount, 2).'</td>
                        </tr>';
            }

            $html .= "
                        <tr class='total-row'>
                            <td colspan='3'><strong>Total Payments</strong></td>
                            <td class='text-right'><strong>".number_format($statementData['total_paid'], 2).'</strong></td>
                        </tr>
                    </tbody>
                </table>
            </div>';
        }

        if ($statementData['credit_notes']->isNotEmpty()) {
            $html .= "
            <div>
                <h3>Credit Notes</h3>
                <table>
                    <thead>
                        <tr>
                            <th>Date</th>
                            <th>Credit Note #</th>
                            <th>Reason</th>
                            <th class='text-right'>Amount</th>
                        </tr>
                    </thead>
                    <tbody>";

            foreach ($statementData['credit_notes'] as $creditNote) {
                $html .= '
                        <tr>
                            <td>'.$creditNote->issue_date->format('M j, Y').'</td>
                            <td>'.$creditNote->credit_note_number.'</td>
                            <td>'.htmlspecialchars($creditNote->reason ?? 'Credit Note')."</td>
                            <td class='text-right'>".number_format($creditNote->total, 2).'</td>
                        </tr>';
            }

            $html .= "
                        <tr class='total-row'>
                            <td colspan='3'><strong>Total Credit Notes</strong></td>
                            <td class='text-right'><strong>".number_format($statementData['total_credit_notes'], 2).'</strong></td>
                        </tr>
                    </tbody>
                </table>
            </div>';
        }

        $aging = $statementData['aging_buckets'];
        $html .= "
            <div class='aging-summary'>
                <h3>Aging Summary as of {$periodEnd->format('M j, Y')}</h3>
                <table>
                    <tr>
                        <th>Current</th>
                        <th>1-30 Days</th>
                        <th>31-60 Days</th>
                        <th>61-90 Days</th>
                        <th>90+ Days</th>
                    </tr>
                    <tr>
                        <td class='text-right'>".number_format($aging['bucket_current'], 2)."</td>
                        <td class='text-right'>".number_format($aging['bucket_1_30'], 2)."</td>
                        <td class='text-right'>".number_format($aging['bucket_31_60'], 2)."</td>
                        <td class='text-right'>".number_format($aging['bucket_61_90'], 2)."</td>
                        <td class='text-right'>".number_format($aging['bucket_90_plus'], 2)."</td>
                    </tr>
                </table>
            </div>
            
            <div style='margin-top: 50px; text-align: center; font-size: 10px; color: #666;'>
                <p>This is a computer-generated statement. If you have any questions, please contact us.</p>
            </div>
        </body>
        </html>";
    }

    /**
     * Generate CSV content for statement.
     */
    private function generateStatementCSV(array $statementData): string
    {
        $customer = $statementData['customer'];
        $periodStart = $statementData['period_start'];
        $periodEnd = $statementData['period_end'];

        $csv = "Customer Statement\n";
        $csv .= "Customer: {$customer->name}\n";
        $csv .= "Period: {$periodStart->format('Y-m-d')} to {$periodEnd->format('Y-m-d')}\n";
        $csv .= 'Generated: '.now()->format('Y-m-d H:i:s')."\n";
        $csv .= "Currency: {$statementData['currency']}\n\n";

        $csv .= "Summary\n";
        $csv .= 'Opening Balance,'.number_format($statementData['opening_balance'], 2)."\n";
        $csv .= 'Total Invoiced,'.number_format($statementData['total_invoiced'], 2)."\n";
        $csv .= 'Total Payments,'.number_format($statementData['total_paid'], 2)."\n";
        $csv .= 'Total Credit Notes,'.number_format($statementData['total_credit_notes'], 2)."\n";
        $csv .= 'Closing Balance,'.number_format($statementData['closing_balance'], 2)."\n\n";

        if ($statementData['invoices']->isNotEmpty()) {
            $csv .= "Invoices\n";
            $csv .= "Date,Invoice Number,Description,Amount\n";

            foreach ($statementData['invoices'] as $invoice) {
                $csv .= $invoice->issue_date->format('Y-m-d').',';
                $csv .= $invoice->invoice_number.',';
                $csv .= '"'.str_replace('"', '""', $invoice->description ?? 'Invoice').'",';
                $csv .= number_format($invoice->total, 2)."\n";
            }
            $csv .= "\n";
        }

        if ($statementData['payments']->isNotEmpty()) {
            $csv .= "Payments\n";
            $csv .= "Date,Payment Number,Method,Amount\n";

            foreach ($statementData['payments'] as $payment) {
                $csv .= $payment->payment_date->format('Y-m-d').',';
                $csv .= $payment->payment_number.',';
                $csv .= ucfirst($payment->payment_method).',';
                $csv .= number_format($payment->amount, 2)."\n";
            }
            $csv .= "\n";
        }

        if ($statementData['credit_notes']->isNotEmpty()) {
            $csv .= "Credit Notes\n";
            $csv .= "Date,Credit Note Number,Reason,Amount\n";

            foreach ($statementData['credit_notes'] as $creditNote) {
                $csv .= $creditNote->issue_date->format('Y-m-d').',';
                $csv .= $creditNote->credit_note_number.',';
                $csv .= '"'.str_replace('"', '""', $creditNote->reason ?? 'Credit Note').'",';
                $csv .= number_format($creditNote->total, 2)."\n";
            }
            $csv .= "\n";
        }

        $aging = $statementData['aging_buckets'];
        $csv .= "Aging Summary as of {$periodEnd->format('Y-m-d')}\n";
        $csv .= "Current,1-30 Days,31-60 Days,61-90 Days,90+ Days\n";
        $csv .= number_format($aging['bucket_current'], 2).',';
        $csv .= number_format($aging['bucket_1_30'], 2).',';
        $csv .= number_format($aging['bucket_31_60'], 2).',';
        $csv .= number_format($aging['bucket_61_90'], 2).',';
        $csv .= number_format($aging['bucket_90_plus'], 2)."\n";

        return $csv;
    }

    /**
     * Generate checksum for statement integrity verification.
     */
    public function generateChecksum(array $statementData): string
    {
        $checksumData = [
            'customer_id' => $statementData['customer']->id,
            'period_start' => $statementData['period_start']->format('Y-m-d'),
            'period_end' => $statementData['period_end']->format('Y-m-d'),
            'opening_balance' => $statementData['opening_balance'],
            'total_invoiced' => $statementData['total_invoiced'],
            'total_paid' => $statementData['total_paid'],
            'total_credit_notes' => $statementData['total_credit_notes'],
            'closing_balance' => $statementData['closing_balance'],
            'generated_at' => $statementData['generated_at']->format('Y-m-d H:i:s'),
        ];

        return hash('sha256', json_encode($checksumData));
    }

    /**
     * Get statement history for a customer.
     */
    public function getStatementHistory(Customer $customer, int $limit = 24): \Illuminate\Database\Eloquent\Collection
    {
        return CustomerStatement::where('customer_id', $customer->id)
            ->where('company_id', $customer->company_id)
            ->orderBy('period_end', 'desc')
            ->limit($limit)
            ->get();
    }

    /**
     * Check if statement already exists for the period.
     */
    public function statementExists(Customer $customer, Carbon $periodStart, Carbon $periodEnd): bool
    {
        return CustomerStatement::where('customer_id', $customer->id)
            ->where('company_id', $customer->company_id)
            ->where('period_start', $periodStart->format('Y-m-d'))
            ->where('period_end', $periodEnd->format('Y-m-d'))
            ->exists();
    }

    /**
     * Validate statement period constraints.
     */
    public function validatePeriod(Carbon $periodStart, Carbon $periodEnd): array
    {
        $errors = [];

        if ($periodEnd->lt($periodStart)) {
            $errors[] = 'Period end date must be after or equal to start date';
        }

        $maxDays = 365;
        if ($periodEnd->diffInDays($periodStart) > $maxDays) {
            $errors[] = "Statement period cannot exceed {$maxDays} days";
        }

        // Prevent future periods
        if ($periodStart->gt(now())) {
            $errors[] = 'Statement period cannot start in the future';
        }

        return $errors;
    }
}
