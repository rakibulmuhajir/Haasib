<?php

namespace App\Modules\FuelStation\Http\Requests;

use App\Constants\Permissions;
use App\Http\Requests\BaseFormRequest;
use App\Services\CompanyContextService;
use Illuminate\Support\Facades\DB;

class StoreFuelProductSetupRequest extends BaseFormRequest
{
    public function authorize(): bool
    {
        if (! $this->validateRlsContext()) {
            return false;
        }

        return $this->hasCompanyPermission(Permissions::FUEL_PRODUCT_SETUP)
            || $this->isCompanyOwnerOrAdmin();
    }

    public function rules(): array
    {
        return [
            'effective_date' => 'required|date',
            'products' => 'required|array|min:1',
            'products.*.type' => 'required|in:fuel,lubricant,other',
            'products.*.name' => 'required|string|max:255',
            'products.*.sku' => 'nullable|string|max:100',
            'products.*.fuel_category' => 'nullable|in:petrol,diesel,high_octane,hi_octane,lubricant',
            'products.*.lubricant_format' => 'nullable|in:open,packaged',
            'products.*.packaging' => 'nullable|in:open,packaged',
            'products.*.category_name' => 'nullable|string|max:255',
            'products.*.unit_of_measure' => 'nullable|string|max:50',
            'products.*.track_inventory' => 'nullable|boolean',
            'products.*.purchase_rate' => 'required|numeric|min:0',
            'products.*.sale_rate' => 'required|numeric|min:0',
            'products.*.opening_quantity' => 'nullable|numeric|min:0',
            'products.*.tank_id' => 'nullable|uuid',
            'products.*.new_tank' => 'nullable|array',
            'products.*.new_tank.name' => 'required_with:products.*.new_tank|string|max:255',
            'products.*.new_tank.code' => 'required_with:products.*.new_tank|string|max:50',
            'products.*.new_tank.capacity' => 'required_with:products.*.new_tank|numeric|min:1',
            'products.*.new_tank.low_level_alert' => 'nullable|numeric|min:0',
            'products.*.pump_setup' => 'nullable|array',
            'products.*.pump_setup.enabled' => 'nullable|boolean',
            'products.*.pump_setup.name' => 'nullable|string|max:100',
            'products.*.pump_setup.nozzle_count' => 'nullable|integer|min:1|max:2',
            'products.*.pump_setup.nozzles' => 'nullable|array',
            'products.*.pump_setup.nozzles.*.code' => 'nullable|string|max:50',
            'products.*.pump_setup.nozzles.*.label' => 'nullable|string|max:255',
            'products.*.pump_setup.nozzles.*.opening_electronic' => 'nullable|numeric|min:0',
            'products.*.pump_setup.nozzles.*.opening_manual' => 'nullable|numeric|min:0',
            'products.*.pump_setups' => 'nullable|array',
            'products.*.pump_setups.*.name' => 'nullable|string|max:100',
            'products.*.pump_setups.*.nozzle_count' => 'nullable|integer|min:1|max:2',
            'products.*.pump_setups.*.nozzles' => 'nullable|array',
            'products.*.pump_setups.*.nozzles.*.code' => 'nullable|string|max:50',
            'products.*.pump_setups.*.nozzles.*.label' => 'nullable|string|max:255',
            'products.*.pump_setups.*.nozzles.*.opening_electronic' => 'nullable|numeric|min:0',
            'products.*.pump_setups.*.nozzles.*.opening_manual' => 'nullable|numeric|min:0',
        ];
    }

    private function isCompanyOwnerOrAdmin(): bool
    {
        $companyId = app(CompanyContextService::class)->getCompanyId();
        $userId = $this->user()?->id;

        if (! $companyId || ! $userId) {
            return false;
        }

        $role = DB::table('auth.company_user')
            ->where('company_id', $companyId)
            ->where('user_id', $userId)
            ->where('is_active', true)
            ->value('role');

        return in_array($role, ['owner', 'admin'], true);
    }
}
