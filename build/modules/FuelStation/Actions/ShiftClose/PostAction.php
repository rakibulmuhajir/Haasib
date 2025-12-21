<?php

namespace App\Modules\FuelStation\Actions\ShiftClose;

use App\Contracts\PaletteAction;
use App\Constants\Permissions;
use App\Modules\FuelStation\Services\ShiftCloseService;

class PostAction implements PaletteAction
{
    public function rules(): array
    {
        return [
            'date' => ['required', 'date'],
            'shift' => ['required', 'in:day,night'],
            'lines' => ['required', 'array', 'min:1'],
            'lines.*.item_id' => ['required', 'uuid', 'exists:inv.items,id'],
            'lines.*.liters_sold' => ['required', 'numeric', 'min:0'],
            'lines.*.sale_rate' => ['required', 'numeric', 'min:0'],

            'cash_amount' => ['sometimes', 'numeric', 'min:0'],
            'easypaisa_amount' => ['sometimes', 'numeric', 'min:0'],
            'jazzcash_amount' => ['sometimes', 'numeric', 'min:0'],
            'bank_transfer_amount' => ['sometimes', 'numeric', 'min:0'],
            'card_swipe_amount' => ['sometimes', 'numeric', 'min:0'],
            'parco_card_amount' => ['sometimes', 'numeric', 'min:0'],

            'notes' => ['nullable', 'string', 'max:255'],
        ];
    }

    public function permission(): ?string
    {
        return Permissions::JOURNAL_CREATE;
    }

    public function handle(array $params): array
    {
        $transaction = app(ShiftCloseService::class)->post($params);

        return [
            'message' => 'Shift close posted to ledger.',
            'data' => [
                'transaction_id' => $transaction->id,
                'transaction_number' => $transaction->transaction_number,
            ],
        ];
    }
}

