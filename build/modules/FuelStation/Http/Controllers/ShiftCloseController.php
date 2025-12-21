<?php

namespace App\Modules\FuelStation\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\Company;
use App\Modules\FuelStation\Http\Requests\StoreShiftCloseRequest;
use App\Modules\FuelStation\Models\AttendantHandover;
use App\Modules\FuelStation\Models\PumpReading;
use App\Modules\FuelStation\Models\RateChange;
use App\Modules\Inventory\Models\Item;
use App\Services\CommandBus;
use App\Services\CurrentCompany;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Inertia\Inertia;
use Inertia\Response;

class ShiftCloseController extends Controller
{
    public function create(Request $request): Response
    {
        /** @var Company $company */
        $company = app(CurrentCompany::class)->get();

        $date = $request->get('date', now()->toDateString());
        $shift = $request->get('shift', 'day');

        $items = Item::where('company_id', $company->id)
            ->whereNotNull('fuel_category')
            ->where('is_active', true)
            ->whereNull('deleted_at')
            ->orderBy('name')
            ->get(['id', 'name', 'fuel_category', 'avg_cost']);

        $rates = [];
        foreach ($items as $item) {
            $rate = RateChange::getRateForDate($company->id, $item->id, $date);
            $rates[$item->id] = [
                'purchase_rate' => (float) ($rate?->purchase_rate ?? 0),
                'sale_rate' => (float) ($rate?->sale_rate ?? 0),
                'effective_date' => $rate?->effective_date?->toDateString(),
            ];
        }

        $pumpReadingTotals = PumpReading::where('company_id', $company->id)
            ->where('reading_date', $date)
            ->where('shift', $shift)
            ->selectRaw('item_id, SUM(liters_dispensed) as liters')
            ->groupBy('item_id')
            ->pluck('liters', 'item_id')
            ->map(fn ($v) => (float) $v)
            ->toArray();

        $handoverTotals = AttendantHandover::where('company_id', $company->id)
            ->whereDate('handover_date', $date)
            ->where('shift', $shift)
            ->selectRaw('
                COALESCE(SUM(cash_amount),0) as cash_amount,
                COALESCE(SUM(easypaisa_amount),0) as easypaisa_amount,
                COALESCE(SUM(jazzcash_amount),0) as jazzcash_amount,
                COALESCE(SUM(bank_transfer_amount),0) as bank_transfer_amount,
                COALESCE(SUM(card_swipe_amount),0) as card_swipe_amount,
                COALESCE(SUM(parco_card_amount),0) as parco_card_amount
            ')
            ->first();

        return Inertia::render('FuelStation/ShiftClose/Create', [
            'company' => [
                'id' => $company->id,
                'name' => $company->name,
                'slug' => $company->slug,
                'base_currency' => $company->base_currency,
            ],
            'date' => $date,
            'shift' => $shift,
            'items' => $items,
            'rates' => $rates,
            'suggested' => [
                'liters_by_item_id' => $pumpReadingTotals,
                'cash_amount' => (float) ($handoverTotals?->cash_amount ?? 0),
                'easypaisa_amount' => (float) ($handoverTotals?->easypaisa_amount ?? 0),
                'jazzcash_amount' => (float) ($handoverTotals?->jazzcash_amount ?? 0),
                'bank_transfer_amount' => (float) ($handoverTotals?->bank_transfer_amount ?? 0),
                'card_swipe_amount' => (float) ($handoverTotals?->card_swipe_amount ?? 0),
                'parco_card_amount' => (float) ($handoverTotals?->parco_card_amount ?? 0),
            ],
        ]);
    }

    public function store(StoreShiftCloseRequest $request): RedirectResponse
    {
        try {
            $result = app(CommandBus::class)->dispatch('fuel.shift_close.post', $request->validated(), $request->user());

            $number = $result['data']['transaction_number'] ?? null;
            $message = $number ? "Shift close posted ({$number})." : ($result['message'] ?? 'Shift close posted.');

            return redirect()->back()->with('success', $message);
        } catch (\Throwable $e) {
            return redirect()->back()->with('error', $e->getMessage());
        }
    }
}

