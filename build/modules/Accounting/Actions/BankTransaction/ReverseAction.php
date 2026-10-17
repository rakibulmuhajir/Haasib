<?php

namespace App\Modules\Accounting\Actions\BankTransaction;

use App\Constants\Permissions;
use App\Contracts\DescribesPalette;
use App\Contracts\PaletteAction;
use App\Facades\CompanyContext;
use App\Modules\Accounting\Services\JournalReversalService;

/**
 * Takes back a typed deposit, withdrawal, transfer or bank charge with a reversal entry dated
 * today (the company's today), unless a date is given. Refused when already reversed or when
 * the day is locked. Needs the same permission as recording one.
 */
class ReverseAction implements DescribesPalette, PaletteAction
{
    public function rules(): array
    {
        return [
            'id' => 'required|uuid',
            'reason' => 'nullable|string|max:500',
            'date' => 'nullable|date',
        ];
    }

    public function permission(): ?string
    {
        return Permissions::BANK_ACCOUNT_UPDATE;
    }

    public function handle(array $params): array
    {
        $company = CompanyContext::requireCompany();
        $done = app(JournalReversalService::class)->reverse($company, JournalReversalService::BANK_TRANSACTION, $params['id'], $params['reason'] ?? null, $params['date'] ?? null);

        return [
            'message' => "Bank transaction reversed: {$done['original']->transaction_number} by {$done['reversal']->transaction_number}",
            'data' => [
                'id' => $done['reversal']->id,
                'transaction_number' => $done['reversal']->transaction_number,
                'reversed_id' => $done['original']->id,
            ],
        ];
    }

    public function palette(): array
    {
        return [
            'description' => 'Reverse a bank or cash movement',
            'fields' => [
                'id' => ['hint' => 'Bank transaction journal id'],
                'date' => ['hint' => 'Reversal date (default today)'],
                'reason' => ['hint' => 'Why'],
            ],
        ];
    }
}
