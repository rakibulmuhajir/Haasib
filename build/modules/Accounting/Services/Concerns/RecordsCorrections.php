<?php

namespace App\Modules\Accounting\Services\Concerns;

use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Shared by CorrectionService (invoices/payments) and BillCorrectionService (bills/bill
 * payments): the advisory lock + app.correction_id wrapper, posting the correcting journal,
 * and recording the row in acct.corrections. A class using this trait must have a
 * `$this->posting` (GlPostingService) property, as both do.
 */
trait RecordsCorrections
{
    private function run(string $companyId, callable $work): array
    {
        return \App\Services\AccountingWriteTransaction::run(function () use ($companyId, $work) {
            DB::selectOne('select pg_advisory_xact_lock(hashtext(?))', ['correction:'.$companyId]);
            DB::select("SELECT set_config('app.correction_id', ?, true)", [(string) Str::uuid()]);

            return $work();
        });
    }

    private function journal(string $companyId, ?string $currency, string $description, array $lines): string
    {
        $currency = strtoupper((string) ($currency ?: 'PKR'));

        return $this->posting->postBalancedTransaction([
            'company_id' => $companyId,
            'transaction_type' => 'correction',
            'date' => now()->toDateString(),
            'currency' => $currency,
            'base_currency' => $currency,
            'description' => $description,
            'reference_type' => 'acct.corrections',
            'reference_id' => null,
        ], $lines)->id;
    }

    private function record(string $companyId, string $entityType, string $entityId, string $action, string $reason, array $changes, ?string $journalId): array
    {
        $last = (int) DB::table('acct.corrections')->where('company_id', $companyId)
            ->selectRaw("max(nullif(regexp_replace(correction_number, '\D', '', 'g'), '')::int) as n")->value('n');
        $number = 'COR-'.str_pad((string) ($last + 1), 5, '0', STR_PAD_LEFT);
        $id = (string) Str::uuid();
        DB::table('acct.corrections')->insert([
            'id' => $id,
            'company_id' => $companyId,
            'correction_number' => $number,
            'entity_type' => $entityType,
            'entity_id' => $entityId,
            'action' => $action,
            'reason' => $reason,
            'changes' => json_encode($changes),
            'transaction_id' => $journalId,
            'created_by_user_id' => Auth::id(),
            'created_at' => now(),
        ]);
        if ($journalId) {
            DB::table('acct.transactions')->where('id', $journalId)->update(['reference_id' => $id]);
        }

        return ['id' => $id, 'number' => $number, 'journal_id' => $journalId, 'changes' => $changes];
    }
}
