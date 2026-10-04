<?php

namespace App\Modules\Inventory\Actions;

use App\Constants\Permissions;
use App\Contracts\PaletteAction;
use App\Modules\Inventory\Models\Item;
use App\Modules\Inventory\Services\ItemPriceService;
use App\Services\CurrentCompany;
use Illuminate\Validation\Rule;

/** item_price.save -- add a price from a date, or replace the one already on that date. */
class SaveItemPriceAction implements PaletteAction
{
    public function __construct(private readonly ItemPriceService $prices) {}

    public function rules(): array
    {
        $company = app(CurrentCompany::class)->getOrFail();

        return [
            'item_id' => ['required', 'uuid', Rule::exists(Item::class, 'id')->where('company_id', $company->id)],
            'effective_date' => ['required', 'date'],
            'sale_price' => ['required', 'numeric', 'min:0', 'max:999999999999'],
            'purchase_price' => ['nullable', 'numeric', 'min:0', 'max:999999999999'],
            'notes' => ['nullable', 'string', 'max:255'],
            'user_id' => ['nullable', 'uuid'],
        ];
    }

    public function permission(): ?string
    {
        return Permissions::ITEM_UPDATE;
    }

    public function handle(array $params): array
    {
        $company = app(CurrentCompany::class)->getOrFail();
        $entry = $this->prices->save(
            $company->id,
            $params['item_id'],
            $params['effective_date'],
            (float) $params['sale_price'],
            isset($params['purchase_price']) ? (float) $params['purchase_price'] : null,
            $params['notes'] ?? null,
            $params['user_id'] ?? null,
        );

        return [
            'message' => 'Price saved.',
            'data' => ['id' => $entry->id],
            'redirect' => "/{$company->slug}/items/{$params['item_id']}",
        ];
    }
}
