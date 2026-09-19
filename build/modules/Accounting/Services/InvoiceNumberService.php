<?php

namespace App\Modules\Accounting\Services;

use App\Models\Company;
use Illuminate\Support\Facades\DB;

class InvoiceNumberService
{
    public function next(string $companyId): string
    {
        $connection = DB::connection('pgsql');
        if ($connection->transactionLevel() === 0) {
            throw new \LogicException('Invoice numbering must run inside the invoice creation transaction.');
        }

        // Lock a stable company key, including when no invoice exists yet. The
        // caller retains this lock until its invoice insert commits or rolls back.
        $connection->select('SELECT pg_advisory_xact_lock(hashtext(?))', ['invoice-number:'.$companyId]);

        $company = Company::findOrFail($companyId);
        $prefix = $company->invoice_prefix ?? 'INV-';
        $prefixLength = mb_strlen($prefix);
        $suffixPosition = $prefixLength + 1;

        // Fuel sales, opening invoices, imports and timestamps must not reset the
        // ordinary sequence. Include deleted invoices so their numbers stay reserved.
        $last = $connection->table('acct.invoices')
            ->where('company_id', $companyId)
            ->whereRaw('left(invoice_number, ?) = ?', [$prefixLength, $prefix])
            ->selectRaw("MAX(CASE WHEN substring(invoice_number from ?::integer) ~ '^[0-9]+$' THEN substring(invoice_number from ?::integer)::numeric END) AS max_number", [$suffixPosition, $suffixPosition])
            ->value('max_number');

        $next = max((int) ($company->invoice_start_number ?? 1001), (int) ($last ?? 0) + 1);

        return $prefix.str_pad((string) $next, 5, '0', STR_PAD_LEFT);
    }
}
