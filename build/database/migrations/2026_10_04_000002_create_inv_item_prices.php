<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Price history for ordinary products: a sale price with only an effective-from date. The price
 * on a day is the latest live entry dated on or before it; a later entry ends the earlier one.
 * Fuels keep fuel.rate_changes.
 *
 * inv.item_price_changes is the trail of who changed what (old -> new). The app has no general
 * audit table, and fuel.daily_close_activity / daily_close_unlocks are fuel specific, so this
 * follows the same append-only pattern.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('inv.item_prices', function (Blueprint $table) {
            $table->uuid('id')->primary()->default(DB::raw('public.gen_random_uuid()'));
            $table->uuid('company_id');
            $table->uuid('item_id');
            $table->date('effective_date');
            $table->decimal('sale_price', 15, 4);
            $table->decimal('purchase_price', 15, 4)->nullable(); // reference only, not stock cost
            $table->string('notes', 255)->nullable();
            $table->uuid('created_by_user_id')->nullable();
            $table->uuid('updated_by_user_id')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->foreign('company_id')->references('id')->on('auth.companies')->cascadeOnDelete()->cascadeOnUpdate();
            $table->foreign('item_id')->references('id')->on('inv.items')->cascadeOnDelete()->cascadeOnUpdate();
            $table->index(['company_id', 'item_id', 'effective_date']);
        });

        DB::statement('CREATE UNIQUE INDEX item_prices_live_date_unique ON inv.item_prices (company_id, item_id, effective_date) WHERE deleted_at IS NULL');
        DB::statement('ALTER TABLE inv.item_prices ADD CONSTRAINT item_prices_sale_price_check CHECK (sale_price >= 0)');
        DB::statement('ALTER TABLE inv.item_prices ADD CONSTRAINT item_prices_purchase_price_check CHECK (purchase_price IS NULL OR purchase_price >= 0)');

        Schema::create('inv.item_price_changes', function (Blueprint $table) {
            $table->uuid('id')->primary()->default(DB::raw('public.gen_random_uuid()'));
            $table->uuid('company_id');
            $table->uuid('item_id');
            $table->date('effective_date');
            $table->string('action', 10); // created | updated | deleted
            $table->decimal('old_sale_price', 15, 4)->nullable();
            $table->decimal('new_sale_price', 15, 4)->nullable();
            $table->decimal('old_purchase_price', 15, 4)->nullable();
            $table->decimal('new_purchase_price', 15, 4)->nullable();
            $table->uuid('changed_by_user_id')->nullable();
            $table->timestamp('changed_at')->useCurrent();

            $table->foreign('company_id')->references('id')->on('auth.companies')->cascadeOnDelete()->cascadeOnUpdate();
            $table->foreign('item_id')->references('id')->on('inv.items')->cascadeOnDelete()->cascadeOnUpdate();
            $table->index(['company_id', 'item_id', 'changed_at']);
        });

        DB::unprepared(<<<'SQL'
CREATE FUNCTION inv.prevent_item_price_change_mutation() RETURNS trigger LANGUAGE plpgsql AS $$
BEGIN
    -- Deleting the whole item or company takes its trail with it; nothing else may rewrite it.
    IF TG_OP = 'DELETE' AND pg_trigger_depth() > 1 THEN RETURN OLD; END IF;
    RAISE EXCEPTION 'Item price history is append-only';
END $$;
CREATE TRIGGER prevent_item_price_change_mutation BEFORE UPDATE OR DELETE ON inv.item_price_changes
FOR EACH ROW EXECUTE FUNCTION inv.prevent_item_price_change_mutation();
SQL);

        foreach (['item_prices', 'item_price_changes'] as $tableName) {
            DB::statement("ALTER TABLE inv.{$tableName} ENABLE ROW LEVEL SECURITY");
            DB::statement("ALTER TABLE inv.{$tableName} FORCE ROW LEVEL SECURITY");

            DB::statement("CREATE POLICY {$tableName}_super_admin ON inv.{$tableName}
                FOR ALL
                USING (
                    current_setting('app.current_user_id', true) IS NOT NULL
                    AND current_setting('app.current_user_id', true)::text LIKE '00000000-0000-0000-0000-%'
                )
                WITH CHECK (
                    current_setting('app.current_user_id', true) IS NOT NULL
                    AND current_setting('app.current_user_id', true)::text LIKE '00000000-0000-0000-0000-%'
                )
            ");

            DB::statement("CREATE POLICY {$tableName}_company_isolation ON inv.{$tableName}
                FOR ALL
                USING (company_id = NULLIF(current_setting('app.current_company_id', true), '')::uuid)
                WITH CHECK (company_id = NULLIF(current_setting('app.current_company_id', true), '')::uuid)
            ");
        }
    }

    public function down(): void
    {
        DB::statement('DROP TRIGGER IF EXISTS prevent_item_price_change_mutation ON inv.item_price_changes');
        DB::statement('DROP FUNCTION IF EXISTS inv.prevent_item_price_change_mutation()');
        Schema::dropIfExists('inv.item_price_changes');
        Schema::dropIfExists('inv.item_prices');
    }
};
