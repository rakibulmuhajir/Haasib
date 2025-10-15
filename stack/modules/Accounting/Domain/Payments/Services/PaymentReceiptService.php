<?php

namespace Modules\Accounting\Domain\Payments\Services;

use Illuminate\Support\Str;
use App\Models\Payment;
use App\Models\PaymentAllocation;
use App\Models\Company;
use App\Models\Customer;

class PaymentReceiptService
{
    /**
     * Generate receipt data for a payment.
     */
    public function generateReceiptData(Payment $payment): array
    {
        $company = Company::findOrFail($payment->company_id);
        $customer = $this->getCustomer($payment->customer_id);
        $allocations = $this->getPaymentAllocations($payment->id);

        return [
            'receipt_number' => 'R-' . $payment->payment_number,
            'company_details' => [
                'name' => $company->name,
                'address' => $company->address ?? '',
                'city' => $company->city ?? '',
                'country' => $company->country ?? '',
                'postal_code' => $company->postal_code ?? '',
                'email' => $company->email ?? '',
                'phone' => $company->phone ?? '',
            ],
            'customer_details' => [
                'name' => $customer->name ?? 'Unknown Customer',
                'email' => $customer->email ?? '',
                'address' => $customer->address ?? '',
                'city' => $customer->city ?? '',
                'country' => $customer->country ?? '',
                'postal_code' => $customer->postal_code ?? '',
            ],
            'payment_details' => [
                'payment_number' => $payment->payment_number,
                'payment_date' => $payment->payment_date,
                'payment_method_label' => $this->formatPaymentMethod($payment->payment_method),
                'reference_number' => $payment->reference_number,
                'notes' => $payment->notes,
            ],
            'amount_summary' => [
                'payment_amount' => (float) $payment->amount,
                'currency_code' => $payment->currency,
                'total_allocated' => (float) $payment->total_allocated,
                'remaining_amount' => (float) $payment->remaining_amount,
                'unallocated_cash_available' => $payment->remaining_amount > 0,
            ],
            'allocations' => $allocations->map(function ($allocation) {
                return [
                    'allocation_id' => $allocation->id,
                    'invoice_number' => $allocation->invoice_number ?? 'N/A',
                    'allocation_date' => $allocation->allocation_date->format('Y-m-d'),
                    'allocated_amount' => (float) ($allocation->allocated_amount ?? 0),
                    'original_amount' => (float) ($allocation->original_amount ?? $allocation->allocated_amount),
                    'discount_amount' => (float) ($allocation->discount_amount ?? 0),
                    'discount_percent' => (float) ($allocation->discount_percent ?? 0),
                    'notes' => $allocation->notes,
                ];
            })->toArray(),
            'generated_at' => now()->toISOString(),
            'total_discount_applied' => (float) $allocations->sum('discount_amount'),
        ];
    }

    /**
     * Generate PDF receipt for a payment.
     */
    public function generatePdfReceipt(Payment $payment): string
    {
        $receiptData = $this->generateReceiptData($payment);
        
        // Generate HTML receipt
        $html = $this->generateReceiptHtml($receiptData);
        
        // Use DomPDF for PDF generation if available, otherwise return HTML
        if (class_exists(\Dompdf\Dompdf::class)) {
            $dompdf = new \Dompdf\Dompdf();
            $dompdf->loadHtml($html);
            $dompdf->setPaper('A4', 'portrait');
            $dompdf->render();
            
            return $dompdf->output();
        }
        
        // Fallback to simple HTML if PDF library not available
        // In production, ensure DomPDF is installed via composer require dompdf/dompdf
        return $html;
    }

    /**
     * Generate receipt HTML content.
     */
    private function generateReceiptHtml(array $receiptData): string
    {
        $currency = $receiptData['amount_summary']['currency_code'];
        
        return "
        <html>
        <head>
            <title>Payment Receipt - {$receiptData['receipt_number']}</title>
            <style>
                body { font-family: Arial, sans-serif; margin: 20px; }
                .header { text-align: center; border-bottom: 2px solid #333; padding-bottom: 20px; }
                .title { font-size: 24px; font-weight: bold; margin-bottom: 10px; }
                .receipt-number { color: #666; font-size: 14px; }
                .section { margin: 20px 0; }
                .grid { display: grid; grid-template-columns: 1fr 1fr; gap: 20px; }
                .label { font-weight: bold; margin-bottom: 5px; }
                .value { margin-bottom: 15px; }
                .allocations { margin-top: 20px; }
                table { width: 100%; border-collapse: collapse; }
                th, td { border: 1px solid #ddd; padding: 8px; text-align: left; }
                th { background-color: #f2f2f2; }
                .total { font-weight: bold; background-color: #f9f9f9; }
                .footer { text-align: center; margin-top: 30px; font-size: 12px; color: #666; }
            </style>
        </head>
        <body>
            <div class='header'>
                <div class='title'>PAYMENT RECEIPT</div>
                <div class='receipt-number'>{$receiptData['receipt_number']}</div>
            </div>
            
            <div class='section'>
                <div class='grid'>
                    <div>
                        <div class='label'>From:</div>
                        <div class='value'><strong>{$receiptData['company_details']['name']}</strong></div>
                        <div class='value'>{$receiptData['company_details']['address']}</div>
                        <div class='value'>{$receiptData['company_details']['city']}, {$receiptData['company_details']['country']} {$receiptData['company_details']['postal_code']}</div>
                    </div>
                    <div>
                        <div class='label'>To:</div>
                        <div class='value'><strong>{$receiptData['customer_details']['name']}</strong></div>
                        <div class='value'>{$receiptData['customer_details']['email']}</div>
                        <div class='value'>{$receiptData['customer_details']['address']}</div>
                    </div>
                </div>
            </div>
            
            <div class='section'>
                <h3>Payment Details</h3>
                <div class='grid'>
                    <div><div class='label'>Payment Number:</div> <div class='value'>{$receiptData['payment_details']['payment_number']}</div></div>
                    <div><div class='label'>Payment Date:</div> <div class='value'>{$receiptData['payment_details']['payment_date']}</div></div>
                    <div><div class='label'>Payment Method:</div> <div class='value'>{$receiptData['payment_details']['payment_method_label']}</div></div>
                    <div><div class='label'>Reference:</div> <div class='value'>{$receiptData['payment_details']['reference_number'] ?: '-'}</div></div>
                </div>
            </div>
            
            <div class='section'>
                <h3>Amount Summary</h3>
                <table>
                    <tr>
                        <td>Payment Amount:</td>
                        <td style='text-align: right'>" . $this->formatMoney($receiptData['amount_summary']['payment_amount'], $currency) . "</td>
                    </tr>
                    <tr>
                        <td>Total Discount Applied:</td>
                        <td style='text-align: right; color: green;'>-" . $this->formatMoney($receiptData['total_discount_applied'], $currency) . "</td>
                    </tr>
                    <tr>
                        <td>Total Allocated:</td>
                        <td style='text-align: right'>" . $this->formatMoney($receiptData['amount_summary']['total_allocated'], $currency) . "</td>
                    </tr>";
                    
        if ($receiptData['amount_summary']['remaining_amount'] > 0) {
            $html .= "
                    <tr>
                        <td>Unallocated Cash:</td>
                        <td style='text-align: right; color: green;'>" . $this->formatMoney($receiptData['amount_summary']['remaining_amount'], $currency) . "</td>
                    </tr>";
        }
                    
        $html .= "
                </table>
            </div>";
            
        if (!empty($receiptData['allocations'])) {
            $html .= "
            <div class='section allocations'>
                <h3>Allocations</h3>
                <table>
                    <thead>
                        <tr>
                            <th>Invoice #</th>
                            <th>Allocation Date</th>
                            <th>Original Amount</th>
                            <th>Discount</th>
                            <th>Allocated Amount</th>
                            <th>Notes</th>
                        </tr>
                    </thead>
                    <tbody>";
            
            foreach ($receiptData['allocations'] as $allocation) {
                $html .= "
                        <tr>
                            <td>{$allocation['invoice_number']}</td>
                            <td>{$allocation['allocation_date']}</td>
                            <td>" . $this->formatMoney($allocation['original_amount'], $currency) . "</td>
                            <td style='color: green;'>" . ($allocation['discount_amount'] > 0 ? '-' . $this->formatMoney($allocation['discount_amount'], $currency) : '-') . "</td>
                            <td>" . $this->formatMoney($allocation['allocated_amount'], $currency) . "</td>
                            <td>{$allocation['notes'] ?: '-'}</td>
                        </tr>";
            }
            
            $html .= "
                    </tbody>
                </table>
            </div>";
        }
        
        $html .= "
            <div class='footer'>
                Generated on {$receiptData['generated_at']}
            </div>
        </body>
        </html>";
        
        return $html;
    }

    /**
     * Get customer information.
     */
    private function getCustomer(string $customerId): object
    {
        // This would typically fetch from the customers table
        // For now, return a basic object
        return (object) [
            'name' => 'Customer Name',
            'email' => 'customer@example.com',
            'address' => '123 Customer St',
            'city' => 'Customer City',
            'country' => 'US',
            'postal_code' => '12345',
        ];
    }

    /**
     * Get payment allocations with invoice details.
     */
    private function getPaymentAllocations(string $paymentId): \Illuminate\Database\Eloquent\Collection
    {
        return PaymentAllocation::where('payment_id', $paymentId)
            ->with(['invoice' => function ($query) {
                $query->select('id', 'invoice_number');
            }])
            ->get();
    }

    /**
     * Format payment method for display.
     */
    private function formatPaymentMethod(string $method): string
    {
        return match($method) {
            'cash' => 'Cash',
            'bank_transfer' => 'Bank Transfer',
            'card' => 'Credit/Debit Card',
            'cheque' => 'Cheque',
            'other' => 'Other',
            default => ucfirst($method),
        };
    }

    /**
     * Format money amount.
     */
    private function formatMoney(float $amount, string $currency): string
    {
        $symbol = match($currency) {
            'USD' => '$',
            'EUR' => '€',
            'GBP' => '£',
            default => $currency . ' ',
        };
        
        return $symbol . number_format($amount, 2);
    }
}