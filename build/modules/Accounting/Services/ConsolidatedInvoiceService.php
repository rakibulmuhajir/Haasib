<?php

namespace App\Modules\Accounting\Services;

use App\Models\Company;
use App\Modules\Accounting\Models\Customer;
use App\Modules\Accounting\Models\Invoice;
use App\Services\CompanyLetterhead;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Consolidated invoices: one document billing a customer for many sales over a period, e.g. a
 * month of daily-close credit sales. A record of what was sent: saved once and never changed,
 * no status. It bills nothing new -- the underlying invoices remain what the customer owes.
 *
 * The lines are the customer's invoice lines in the period (date, invoice, reference, what,
 * litres, rate, amount); the user picks which go on it, can add columns (vehicle, driver ...)
 * and fill them in, and can address it to a person or office and sign it. Everything is saved
 * exactly as sent, so a reprint is the same paper.
 */
class ConsolidatedInvoiceService
{
    /**
     * The customer's invoices dated in the range, one row per line. Line totals are already net
     * of any discount. Each row says which consolidated invoice last carried it, if any.
     *
     * @return array<int,array<string,mixed>>
     */
    public function rowsFor(string $companyId, string $customerId, string $from, string $to): array
    {
        $invoices = Invoice::where('company_id', $companyId)
            ->where('customer_id', $customerId)
            ->whereNotIn('status', ['draft', 'void', 'cancelled'])
            ->whereBetween('invoice_date', [$from, $to])
            ->with('lineItems')
            ->orderBy('invoice_date')
            ->orderBy('invoice_number')
            ->get();

        $sent = DB::table('acct.consolidated_invoice_items as i')
            ->join('acct.consolidated_invoices as c', 'c.id', '=', 'i.consolidated_invoice_id')
            ->where('i.company_id', $companyId)
            ->whereIn('i.invoice_id', $invoices->pluck('id'))
            ->orderBy('c.created_at')
            ->get(['i.invoice_id', 'c.number', 'c.created_at'])
            ->keyBy('invoice_id'); // latest wins

        $rows = [];
        foreach ($invoices as $invoice) {
            // A daily close's credit sale keeps the slip number at the end of its notes.
            $reference = preg_match('/^Credit portion of meter sales for [0-9-]+\.\s*(.+)$/', (string) $invoice->notes, $m) ? trim($m[1]) : null;
            $last = $sent[$invoice->id] ?? null;
            foreach ($invoice->lineItems->sortBy('line_number') as $line) {
                $rows[] = [
                    'key' => $line->id,
                    'invoice_id' => $invoice->id,
                    'invoice_number' => $invoice->invoice_number,
                    'date' => $invoice->invoice_date?->toDateString(),
                    'reference' => $reference,
                    'paid' => (float) $invoice->balance <= 0.005,
                    'balance' => round((float) $invoice->balance, 2),
                    'sent_in' => $last ? ['number' => $last->number, 'date' => substr((string) $last->created_at, 0, 10)] : null,
                    'description' => (string) $line->description,
                    'quantity' => $line->quantity !== null ? round((float) $line->quantity, 2) : null,
                    'rate' => $line->unit_price !== null ? round((float) $line->unit_price, 2) : null,
                    'amount' => round((float) $line->total, 2),
                ];
            }
        }

        return $rows;
    }

    /** Bill to, from the customer record: name, billing contact, phone, address. */
    public function billTo(Customer $customer): array
    {
        $address = is_array($customer->billing_address) ? $customer->billing_address : [];

        return [
            'name' => $customer->name,
            'attention' => $customer->billing_contact ?? '',
            'phone' => $customer->phone ?? '',
            'lines' => array_values(array_filter([
                $address['line1'] ?? $address['street'] ?? null,
                $address['line2'] ?? null,
                trim(($address['city'] ?? '').' '.($address['postal_code'] ?? $address['zip'] ?? '')) ?: null,
            ])),
        ];
    }

    /** Who signs: set once in company settings. */
    public function billedBy(Company $company): array
    {
        $settings = is_array($company->settings) ? $company->settings : [];

        return [
            'name' => $settings['billed_by_name'] ?? '',
            'designation' => $settings['billed_by_designation'] ?? '',
            'phone' => $settings['billed_by_phone'] ?? '',
        ];
    }

    /**
     * Saves the document as sent. The rows are re-read here, so only this customer's invoices in
     * the period can be on it, whatever the form sent.
     */
    public function create(Company $company, array $data, ?string $userId): string
    {
        $customer = Customer::where('company_id', $company->id)->findOrFail($data['customer_id']);
        $keys = array_flip(array_map('strval', $data['keys'] ?? []));
        $references = $data['references'] ?? [];
        $text = fn ($v, int $max = 120) => mb_substr(trim((string) $v), 0, $max);

        $columns = array_values(array_map(fn ($c) => [
            'label' => $text($c['label'] ?? '', 60),
            'values' => array_map(fn ($v) => $text($v, 200), (array) ($c['values'] ?? [])),
        ], $data['columns'] ?? []));

        $rows = array_values(array_filter($this->rowsFor($company->id, $customer->id, $data['from'], $data['to']), fn ($r) => isset($keys[$r['key']])));
        if (! $rows) {
            throw ValidationException::withMessages(['keys' => 'Pick at least one line.']);
        }
        // Keep the columns someone named or filled in.
        $columns = array_values(array_filter($columns, fn ($c) => $c['label'] !== ''
            || collect($rows)->contains(fn ($r) => ($c['values'][$r['key']] ?? '') !== '')));

        $lines = array_map(fn ($r) => [
            'invoice_id' => $r['invoice_id'],
            'invoice_number' => $r['invoice_number'],
            'date' => $r['date'],
            'reference' => $text($references[$r['key']] ?? $r['reference'] ?? '', 100),
            'description' => $r['description'],
            'quantity' => $r['quantity'],
            'rate' => $r['rate'],
            'amount' => $r['amount'],
            'extra' => array_map(fn ($c) => $c['values'][$r['key']] ?? '', $columns),
        ], $rows);

        $fallback = $this->billTo($customer);
        $billTo = [
            'name' => $text($data['bill_to']['name'] ?? '') ?: $fallback['name'],
            'attention' => $text($data['bill_to']['attention'] ?? ''),
            'phone' => $text($data['bill_to']['phone'] ?? '', 50),
            'lines' => $fallback['lines'],
        ];
        $billedBy = [
            'name' => $text($data['billed_by']['name'] ?? ''),
            'designation' => $text($data['billed_by']['designation'] ?? ''),
            'phone' => $text($data['billed_by']['phone'] ?? '', 50),
        ];

        return DB::transaction(function () use ($company, $customer, $data, $text, $billTo, $billedBy, $columns, $lines, $userId) {
            DB::select('SELECT pg_advisory_xact_lock(hashtext(?), hashtext(?))', [$company->id, 'consolidated_invoice_number']);
            $count = DB::table('acct.consolidated_invoices')->where('company_id', $company->id)->count();
            $id = (string) Str::uuid();
            DB::table('acct.consolidated_invoices')->insert([
                'id' => $id,
                'company_id' => $company->id,
                'number' => 'CI-'.str_pad((string) ($count + 1), 5, '0', STR_PAD_LEFT),
                'customer_id' => $customer->id,
                'period_from' => $data['from'],
                'period_to' => $data['to'],
                'title' => $text($data['title'] ?? '', 60) ?: 'Invoice',
                'bill_to' => json_encode($billTo),
                'billed_by' => json_encode($billedBy),
                'columns' => json_encode(array_column($columns, 'label')),
                'lines' => json_encode($lines),
                'total' => round(array_sum(array_column($lines, 'amount')), 2),
                'currency' => $company->base_currency ?: 'PKR',
                'created_by_user_id' => $userId,
                'created_at' => now(),
            ]);
            DB::table('acct.consolidated_invoice_items')->insert(array_map(fn ($invoiceId) => [
                'id' => (string) Str::uuid(), 'company_id' => $company->id, 'consolidated_invoice_id' => $id, 'invoice_id' => $invoiceId,
            ], array_values(array_unique(array_column($lines, 'invoice_id')))));

            return $id;
        });
    }

    /** A saved document, ready for the page and the PDF. */
    public function document(Company $company, string $id): array
    {
        $doc = DB::table('acct.consolidated_invoices as c')
            ->join('acct.customers as cu', 'cu.id', '=', 'c.customer_id')
            ->leftJoin('auth.users as u', 'u.id', '=', 'c.created_by_user_id')
            ->where('c.company_id', $company->id)
            ->where('c.id', $id)
            ->first(['c.*', 'cu.name as customer_name', 'u.name as created_by_name']);
        abort_unless($doc, 404);
        $lines = json_decode($doc->lines, true);

        return [
            'id' => $doc->id,
            'number' => $doc->number,
            'customer_id' => $doc->customer_id,
            'customer_name' => $doc->customer_name,
            'period_from' => substr((string) $doc->period_from, 0, 10),
            'period_to' => substr((string) $doc->period_to, 0, 10),
            'date' => substr((string) $doc->created_at, 0, 10),
            'created_by_name' => $doc->created_by_name,
            'title' => $doc->title,
            'bill_to' => json_decode($doc->bill_to, true),
            'billed_by' => json_decode($doc->billed_by, true),
            'columns' => json_decode($doc->columns, true),
            'lines' => $lines,
            'total' => (float) $doc->total,
            'currency' => $doc->currency,
            'show_reference' => collect($lines)->contains(fn ($l) => ($l['reference'] ?? '') !== ''),
            'show_quantity' => collect($lines)->contains(fn ($l) => $l['quantity'] !== null),
            'issuer' => app(CompanyLetterhead::class)->forCompany($company),
        ];
    }

    public function pdf(array $document): string
    {
        $html = view()->file(base_path('modules/Accounting/Resources/views/consolidated-invoice.blade.php'), ['doc' => $document])->render();

        // Only the characters used are embedded: the whole font made a one-page file ~900 KB.
        return \Barryvdh\DomPDF\Facade\Pdf::loadHTML($html)->setPaper('a4')->setOption('isFontSubsettingEnabled', true)->output();
    }
}
