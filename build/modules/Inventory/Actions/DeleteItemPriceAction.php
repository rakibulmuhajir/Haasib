<?php

namespace App\Modules\Inventory\Actions;

use App\Constants\Permissions;
use App\Contracts\PaletteAction;
use App\Modules\Inventory\Models\ItemPrice;
use App\Modules\Inventory\Services\ItemPriceService;
use App\Services\CurrentCompany;
use Illuminate\Validation\Rule;

/** item_price.delete -- remove a price entry; the one before it carries on. */
class DeleteItemPriceAction implements PaletteAction
{
    public function __construct(private readonly ItemPriceService $prices) {}

    public function rules(): array
    {
        $company = app(CurrentCompany::class)->getOrFail();

        return [
            'price_id' => ['required', 'uuid', Rule::exists(ItemPrice::class, 'id')->where('company_id', $company->id)->whereNull('deleted_at')],
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
        $this->prices->delete($company->id, $params['price_id'], $params['user_id'] ?? null);

        return ['message' => 'Price removed.', 'data' => ['id' => $params['price_id']]];
    }
}
