<?php

namespace App\Modules\Accounting\Actions\Expense;

use App\Constants\Permissions;
use App\Contracts\DescribesPalette;
use App\Contracts\PaletteAction;
use App\Facades\CompanyContext;
use App\Modules\Accounting\Services\JournalReversalService;

/**
 * Takes back a standalone expense with a reversal entry dated today (the company's today),
 * unless a date is given. Refused when already reversed or when the day is locked.
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
        // Whoever may record an expense may undo it (owner's decision, 2026-10-17).
        return Permissions::EXPENSE_CREATE;
    }

    public function handle(array $params): array
    {
        $company = CompanyContext::requireCompany();
        $done = app(JournalReversalService::class)->reverse($company, JournalReversalService::EXPENSE, $params['id'], $params['reason'] ?? null, $params['date'] ?? null);

        return [
            'message' => "Expense reversed: {$done['original']->transaction_number} by {$done['reversal']->transaction_number}",
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
            'description' => 'Reverse an expense',
            'fields' => [
                'id' => ['hint' => 'Expense journal id'],
                'date' => ['hint' => 'Reversal date (default today)'],
                'reason' => ['hint' => 'Why'],
            ],
        ];
    }
}
