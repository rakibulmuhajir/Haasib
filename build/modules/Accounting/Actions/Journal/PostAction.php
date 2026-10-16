<?php

namespace App\Modules\Accounting\Actions\Journal;

use App\Constants\Permissions;
use App\Contracts\DescribesPalette;
use App\Contracts\PaletteAction;
use App\Facades\CompanyContext;
use App\Modules\Accounting\Models\AccountingPeriod;
use App\Modules\Accounting\Models\Transaction;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;

/**
 * Posts a draft manual journal. Posting needs the same right as writing one.
 */
class PostAction implements PaletteAction, DescribesPalette
{
    public function rules(): array
    {
        return [
            'id' => ['required', 'uuid', Rule::exists(Transaction::class, 'id')],
        ];
    }

    public function permission(): ?string
    {
        return Permissions::JOURNAL_CREATE;
    }

    public function handle(array $params): array
    {
        $company = CompanyContext::requireCompany();

        $transaction = Transaction::where('company_id', $company->id)->findOrFail($params['id']);

        if ($transaction->status !== 'draft') {
            throw new \RuntimeException('Only a draft journal can be posted');
        }

        $open = AccountingPeriod::where('company_id', $company->id)
            ->where('is_closed', false)
            ->where('start_date', '<=', $transaction->transaction_date)
            ->where('end_date', '>=', $transaction->transaction_date)
            ->exists();

        if (! $open) {
            throw new \RuntimeException('No open accounting period for this date');
        }

        if (abs((float) $transaction->total_debit - (float) $transaction->total_credit) > 0.0001) {
            throw new \RuntimeException('Journal must balance (debits = credits)');
        }

        $transaction->update([
            'status' => 'posted',
            'posted_at' => now(),
            'posted_by_user_id' => Auth::id(),
        ]);

        return [
            'message' => "Journal {$transaction->transaction_number} posted",
            'data' => [
                'id' => $transaction->id,
                'number' => $transaction->transaction_number,
                'status' => $transaction->status,
            ],
        ];
    }

    public function palette(): array
    {
        return [
            'description' => 'Post a draft journal',
            'hidden' => true,
        ];
    }
}
