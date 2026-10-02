<?php

namespace App\Modules\FuelStation\Http\Requests;

use App\Constants\Permissions;
use App\Http\Requests\BaseFormRequest;
use App\Modules\FuelStation\Models\Nozzle;
use App\Modules\Inventory\Models\Item;
use App\Modules\Inventory\Models\Warehouse;
use Illuminate\Validation\Rule;

class StoreRateChangeRequest extends BaseFormRequest
{
    public function authorize(): bool
    {
        return $this->hasCompanyPermission(Permissions::FUEL_RATE_UPDATE)
            && $this->validateRlsContext();
    }

    public function rules(): array
    {
        return [
            'item_id' => ['required', 'uuid', Rule::exists(Item::class, 'id')],
            'effective_date' => ['required', 'date'],
            'purchase_rate' => ['required', 'numeric', 'min:0'],
            'sale_rate' => ['required', 'numeric', 'min:0'],
            'stock_quantity_at_change' => ['nullable', 'numeric', 'min:0'],
            'snapshot_tank_id' => ['nullable', 'uuid', Rule::exists(Warehouse::class, 'id')],
            'snapshot_stick_reading' => ['nullable', 'numeric', 'min:0'],
            'snapshot_dip_liters' => ['nullable', 'numeric', 'min:0'],
            'snapshot_nozzle_readings' => ['nullable', 'array'],
            'snapshot_nozzle_readings.*.nozzle_id' => ['required_with:snapshot_nozzle_readings', 'uuid', Rule::exists(Nozzle::class, 'id')],
            'snapshot_nozzle_readings.*.electronic_reading' => ['nullable', 'numeric', 'min:0'],
            'snapshot_nozzle_readings.*.manual_reading' => ['nullable', 'numeric', 'min:0'],
            'notes' => ['nullable', 'string', 'max:1000'],
        ];
    }

    /**
     * A rate from a day that is already closed would reprice a posted day behind its back: the
     * close keeps its own rates, but anything that later asks "what was the rate that day" -- a
     * reopen of it, the rates history -- would get the new one. That is how diesel's 385.5,
     * applied from a form still showing 7 Sep after 7 Sep was posted, came back on reopening
     * 7 Sep. A reopened day is a draft again, so it can still take a rate change.
     */
    public function after(): array
    {
        return [function ($validator) {
            $date = $this->input('effective_date');
            if ($validator->errors()->has('effective_date') || ! $date) {
                return;
            }
            $companyId = app(\App\Services\CurrentCompany::class)->get()?->id;
            $closed = $companyId && \App\Modules\Accounting\Models\Transaction::where('company_id', $companyId)
                ->where('transaction_type', 'fuel_daily_close')
                ->whereIn('status', ['posted', 'locked'])
                ->whereNull('deleted_at')
                ->whereNull('reversed_by_id')
                ->whereDate('transaction_date', $date)
                ->exists();
            if ($closed) {
                $validator->errors()->add('effective_date', \Illuminate\Support\Carbon::parse($date)->format('j M').' is already closed.');
            }
        }];
    }
}
