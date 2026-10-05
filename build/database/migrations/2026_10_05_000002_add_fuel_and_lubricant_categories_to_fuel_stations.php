<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Every fuel station gets two product categories, "Fuel" and "Lubricant", and its existing
 * products are filed under them: a product with a tank, or a petrol / diesel / high-octane
 * fuel category, goes under Fuel; a lubricant (open drum, or sealed packs on the lubricant
 * stock accounts, or named so) goes under Lubricant. A product that already has a category
 * keeps it, and a category the company already has by that name is reused.
 */
return new class extends Migration
{
    private const FUELS = ['petrol', 'diesel', 'high_octane', 'hi_octane'];

    public function up(): void
    {
        $companyIds = DB::table('auth.companies')->where('industry_code', 'fuel_station')->pluck('id');

        foreach ($companyIds as $companyId) {
            // Tenant context, in case row-level security is enforced.
            DB::statement("SELECT set_config('app.current_company_id', ?, false)", [$companyId]);

            $fuel = $this->ensureCategory($companyId, 'Fuel', 'FUEL', 1);
            $lubricant = $this->ensureCategory($companyId, 'Lubricant', 'LUBRICANT', 2);

            // A warehouse linked to one product is that product's tank.
            $tankItems = DB::table('inv.warehouses')->where('company_id', $companyId)
                ->whereNotNull('linked_item_id')->pluck('linked_item_id')->all();
            $lubricantAccounts = DB::table('acct.accounts')->where('company_id', $companyId)
                ->whereIn('code', ['1250', '1251', '4150', '4151', '5150', '5151'])->pluck('id')->all();

            $items = DB::table('inv.items')->where('company_id', $companyId)->whereNull('category_id')
                ->whereNull('deleted_at')
                ->get(['id', 'name', 'fuel_category', 'asset_account_id', 'income_account_id', 'expense_account_id']);

            foreach ($items as $item) {
                $isLubricant = $item->fuel_category === 'lubricant'
                    || in_array($item->asset_account_id, $lubricantAccounts, true)
                    || in_array($item->income_account_id, $lubricantAccounts, true)
                    || in_array($item->expense_account_id, $lubricantAccounts, true)
                    || stripos((string) $item->name, 'lubric') !== false;
                $isFuel = ! $isLubricant
                    && (in_array($item->fuel_category, self::FUELS, true) || in_array($item->id, $tankItems, true));

                $target = $isLubricant ? $lubricant : ($isFuel ? $fuel : null);
                if ($target) {
                    DB::table('inv.items')->where('id', $item->id)->update(['category_id' => $target, 'updated_at' => now()]);
                }
            }
        }

        DB::statement("SELECT set_config('app.current_company_id', '', false)");
    }

    public function down(): void
    {
        // Categories are the owner's to keep; nothing to undo.
    }

    private function ensureCategory(string $companyId, string $name, string $code, int $sort): string
    {
        $existing = DB::table('inv.item_categories')->where('company_id', $companyId)->whereNull('deleted_at')
            ->whereRaw('lower(name) = ?', [Str::lower($name)])->value('id');
        if ($existing) {
            return $existing;
        }

        // The code is unique per company; take a free one if the company already uses it.
        $free = $code;
        $n = 1;
        while (DB::table('inv.item_categories')->where('company_id', $companyId)->where('code', $free)->whereNull('deleted_at')->exists()) {
            $free = $code.'_'.$n++;
        }

        $id = (string) Str::uuid();
        DB::table('inv.item_categories')->insert([
            'id' => $id,
            'company_id' => $companyId,
            'code' => $free,
            'name' => $name,
            'is_active' => true,
            'sort_order' => $sort,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return $id;
    }
};
