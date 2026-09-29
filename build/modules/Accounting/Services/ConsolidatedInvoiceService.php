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
 * One standard layout: date, reference (from the close's Sale row), invoice no. (the station's
 * own physical invoice / coupon, typed here when the customer wants it), fuel, litres, rate,
 * amount. The user picks which of the customer's unpaid invoice lines go on it, addresses it and
 * signs it. Everything is saved exactly as sent, so a reprint is the same paper.
 */
class ConsolidatedInvoiceService
{
    /**
     * The customer's unpaid invoices dated in the range, one row per line -- a paid invoice has
     * nothing left to bill. Line totals are already net of any discount. Each row says which
     * consolidated invoice last carried it, if any.
     *
     * @return array<int,array<string,mixed>>
     */
    public function rowsFor(string $companyId, string $customerId, string $from, string $to): array
    {
        $invoices = Invoice::where('company_id', $companyId)
            ->where('customer_id', $customerId)
            ->whereNotIn('status', ['draft', 'void', 'cancelled'])
            ->where('balance', '>', 0.005)
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

        // What each line sold. Invoice lines keep no product, so, in order: the daily close's own
        // record of each credit sale (invoice -> fuel item); a fuel named in the line's description
        // ("994 L Diesel - direct from tanker"); the product whose sales account the line posts to.
        $items = DB::table('inv.items')->where('company_id', $companyId)->whereNull('deleted_at')
            ->get(['id', 'name', 'fuel_category', 'income_account_id']);
        $itemName = $items->pluck('name', 'id');
        $closeItem = [];
        DB::table('acct.transactions')->where('company_id', $companyId)->where('transaction_type', 'fuel_daily_close')
            ->whereNull('deleted_at')->whereBetween('transaction_date', [$from, $to])->pluck('metadata')
            ->each(function ($metadata) use (&$closeItem) {
                foreach ((json_decode((string) $metadata, true)['credit_sale_details'] ?? []) as $sale) {
                    if (! empty($sale['invoice_id']) && ! empty($sale['item_id'])) {
                        $closeItem[$sale['invoice_id']] = $sale['item_id'];
                    }
                }
            });
        $fuelNames = $items->whereNotNull('fuel_category')->pluck('name')->sortByDesc(fn ($n) => mb_strlen($n))->values();
        $byAccount = $items->whereNotNull('income_account_id')->groupBy('income_account_id')
            ->map(fn ($group) => $group->count() === 1 ? $group->first()->name : null);
        $itemFor = function ($invoice, $line) use ($closeItem, $itemName, $fuelNames, $byAccount): string {
            if (isset($closeItem[$invoice->id], $itemName[$closeItem[$invoice->id]])) {
                return $itemName[$closeItem[$invoice->id]];
            }
            foreach ($fuelNames as $name) {
                if (mb_stripos((string) $line->description, $name) !== false) {
                    return $name;
                }
            }

            return (string) ($byAccount[$line->income_account_id] ?? '');
        };

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
                    'item' => $itemFor($invoice, $line),
                    'description' => (string) $line->description,
                    'quantity' => $line->quantity !== null ? round((float) $line->quantity, 2) : null,
                    'rate' => $line->unit_price !== null ? round((float) $line->unit_price, 2) : null,
                    'amount' => round((float) $line->total, 2),
                ];
            }
        }

        return $rows;
    }

    /** The consolidated invoices sent to a customer, newest first, for their page. */
    public function forCustomer(string $companyId, string $customerId, int $limit = 10): array
    {
        return DB::table('acct.consolidated_invoices')
            ->where('company_id', $companyId)
            ->where('customer_id', $customerId)
            ->orderByDesc('created_at')
            ->limit($limit)
            ->get(['id', 'number', 'title', 'created_at', 'period_from', 'period_to', 'total', DB::raw('jsonb_array_length(lines) as line_count')])
            ->map(fn ($d) => [
                'id' => $d->id, 'number' => $d->number, 'title' => $d->title,
                'date' => substr((string) $d->created_at, 0, 10),
                'period_from' => substr((string) $d->period_from, 0, 10), 'period_to' => substr((string) $d->period_to, 0, 10),
                'total' => (float) $d->total, 'line_count' => (int) $d->line_count,
            ])->all();
    }

    /**
     * An address on one line: street, line 2, city, state, postal code, country, the empty parts
     * left out. Customer and company addresses name some parts differently (street / line1,
     * zip / postal_code); both are read.
     */
    public static function addressLine(mixed $address): string
    {
        $address = is_array($address) ? $address : [];
        $parts = [
            $address['line1'] ?? $address['street'] ?? null,
            $address['line2'] ?? null,
            $address['city'] ?? null,
            $address['state'] ?? null,
            $address['postal_code'] ?? $address['zip'] ?? null,
            $address['country'] ?? null,
        ];

        return implode(', ', array_filter(array_map(fn ($p) => is_string($p) ? trim($p) : '', $parts), fn ($p) => $p !== ''));
    }

    /** Bill to, from the customer record: name, billing contact, phone, billing address. */
    public function billTo(Customer $customer): array
    {
        return [
            'name' => $customer->name,
            'attention' => $customer->billing_contact ?? '',
            'phone' => $customer->phone ?? '',
            'address' => self::addressLine($customer->billing_address),
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
            'address' => self::addressLine($company->address),
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
        $physical = $data['physical'] ?? [];
        $text = fn ($v, int $max = 120) => mb_substr(trim((string) $v), 0, $max);

        $rows = array_values(array_filter($this->rowsFor($company->id, $customer->id, $data['from'], $data['to']), fn ($r) => isset($keys[$r['key']])));
        if (! $rows) {
            throw ValidationException::withMessages(['keys' => 'Pick at least one line.']);
        }
        $lines = array_map(fn ($r) => [
            'invoice_id' => $r['invoice_id'],
            'invoice_number' => $r['invoice_number'],
            'date' => $r['date'],
            'reference' => $text($references[$r['key']] ?? $r['reference'] ?? '', 100),
            'physical_invoice' => $text($physical[$r['key']] ?? '', 60),
            'item' => $r['item'],
            'description' => $r['description'],
            'quantity' => $r['quantity'],
            'rate' => $r['rate'],
            'amount' => $r['amount'],
        ], $rows);

        $fallback = $this->billTo($customer);
        $billTo = [
            'name' => $text($data['bill_to']['name'] ?? '') ?: $fallback['name'],
            'attention' => $text($data['bill_to']['attention'] ?? ''),
            'phone' => $text($data['bill_to']['phone'] ?? '', 50),
            'address' => $text($data['bill_to']['address'] ?? '', 300),
        ];
        $billedBy = [
            'name' => $text($data['billed_by']['name'] ?? ''),
            'designation' => $text($data['billed_by']['designation'] ?? ''),
            'phone' => $text($data['billed_by']['phone'] ?? '', 50),
            'address' => $text($data['billed_by']['address'] ?? '', 300),
        ];

        return DB::transaction(function () use ($company, $customer, $data, $text, $billTo, $billedBy, $lines, $userId) {
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
                'columns' => json_encode([]),
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
            'lines' => $lines,
            // Reference and the station's invoice no. print only when something is in them.
            'show_reference' => collect($lines)->contains(fn ($l) => ($l['reference'] ?? '') !== ''),
            'show_physical' => collect($lines)->contains(fn ($l) => ($l['physical_invoice'] ?? '') !== ''),
            'total' => (float) $doc->total,
            'currency' => $doc->currency,
            'issuer' => app(CompanyLetterhead::class)->forCompany($company),
        ];
    }

    public function pdf(array $document): string
    {
        // The PDF renderer reads files, not addresses: embed an uploaded logo as data.
        $logo = (string) parse_url((string) ($document['issuer']['logoUrl'] ?? ''), PHP_URL_PATH);
        $path = str_starts_with($logo, '/storage/') ? storage_path('app/public/'.substr($logo, strlen('/storage/'))) : null;
        $document['logo_data'] = $path && is_file($path)
            ? 'data:'.(mime_content_type($path) ?: 'image/png').';base64,'.base64_encode((string) file_get_contents($path))
            : null;

        $html = view()->file(base_path('modules/Accounting/Resources/views/consolidated-invoice.blade.php'), ['doc' => $document])->render();

        // Only the characters used are embedded: the whole font made a one-page file ~900 KB.
        return \Barryvdh\DomPDF\Facade\Pdf::loadHTML($html)->setPaper('a4')->setOption('isFontSubsettingEnabled', true)->output();
    }
}
