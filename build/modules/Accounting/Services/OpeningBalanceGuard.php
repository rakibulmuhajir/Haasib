<?php

namespace App\Modules\Accounting\Services;

use App\Models\Company;
use App\Modules\Accounting\Models\Transaction;

/** Always read persisted settings: a context Company can predate the lock. */
class OpeningBalanceGuard
{
    public function assertMutable(string $companyId, string $kind, string $id): void
    {
        $opening = Company::whereKey($companyId)->firstOrFail()->settings['opening_balances'] ?? [];
        if (empty($opening['locked_at'])) {
            return;
        }
        $ids = array_merge($opening[$kind.'_ids'] ?? [], $opening['retired_'.$kind.'_ids'] ?? []);
        if ($kind === 'journal') {
            $ids[] = $opening['journal_id'] ?? null;
            $transaction = Transaction::withTrashed()->where('company_id', $companyId)->find($id);
            if ($transaction && in_array($transaction->reference_type, ['acct.invoices', 'acct.bills'], true)) {
                $this->assertMutable($companyId, $transaction->reference_type === 'acct.invoices' ? 'invoice' : 'bill', $transaction->reference_id);
            }
        }
        if (in_array($id, $ids, true)) {
            throw new \RuntimeException('This is a locked opening-balance record and cannot be changed or reversed.');
        }
    }
}
