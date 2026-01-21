<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('inv.stock_receipts', function (Blueprint $table) {
            $table->uuid('id')->primary()->default(DB::raw('public.gen_random_uuid()'));
            $table->uuid('company_id');
            $table->uuid('bill_id')->nullable();
            $table->date('receipt_date')->default(DB::raw('CURRENT_DATE'));
            $table->text('notes')->nullable();
            $table->uuid('variance_transaction_id')->nullable();
            $table->uuid('created_by_user_id')->nullable();
            $table->timestamps();

            $table->foreign('company_id')
                ->references('id')->on('auth.companies')
                ->cascadeOnDelete()->cascadeOnUpdate();
            $table->foreign('bill_id')
                ->references('id')->on('acct.bills')
                ->nullOnDelete()->cascadeOnUpdate();
            $table->foreign('variance_transaction_id')
                ->references('id')->on('acct.transactions')
                ->nullOnDelete()->cascadeOnUpdate();
            $table->foreign('created_by_user_id')
                ->references('id')->on('auth.users')
                ->nullOnDelete()->cascadeOnUpdate();

            $table->index('company_id');
            $table->index('bill_id');
            $table->index('receipt_date');
        });

        Schema::create('inv.stock_receipt_lines', function (Blueprint $table) {
            $table->uuid('id')->primary()->default(DB::raw('public.gen_random_uuid()'));
            $table->uuid('company_id');
            $table->uuid('stock_receipt_id');
            $table->uuid('bill_line_item_id')->nullable();
            $table->uuid('item_id');
            $table->uuid('warehouse_id');
            $table->decimal('expected_quantity', 18, 3);
            $table->decimal('received_quantity', 18, 3);
            $table->decimal('variance_quantity', 18, 3)->default(0);
            $table->decimal('unit_cost', 15, 6);
            $table->decimal('total_cost', 15, 2);
            $table->decimal('variance_cost', 15, 2)->default(0);
            $table->string('variance_reason', 50)->nullable();
            $table->uuid('stock_movement_id')->nullable();
            $table->text('notes')->nullable();
            $table->uuid('created_by_user_id')->nullable();
            $table->timestamps();

            $table->foreign('company_id')
                ->references('id')->on('auth.companies')
                ->cascadeOnDelete()->cascadeOnUpdate();
            $table->foreign('stock_receipt_id')
                ->references('id')->on('inv.stock_receipts')
                ->cascadeOnDelete()->cascadeOnUpdate();
            $table->foreign('bill_line_item_id')
                ->references('id')->on('acct.bill_line_items')
                ->nullOnDelete()->cascadeOnUpdate();
            $table->foreign('item_id')
                ->references('id')->on('inv.items')
                ->restrictOnDelete()->cascadeOnUpdate();
            $table->foreign('warehouse_id')
                ->references('id')->on('inv.warehouses')
                ->restrictOnDelete()->cascadeOnUpdate();
            $table->foreign('stock_movement_id')
                ->references('id')->on('inv.stock_movements')
                ->nullOnDelete()->cascadeOnUpdate();
            $table->foreign('created_by_user_id')
                ->references('id')->on('auth.users')
                ->nullOnDelete()->cascadeOnUpdate();

            $table->index('company_id');
            $table->index('stock_receipt_id');
            $table->index('bill_line_item_id');
            $table->index('item_id');
            $table->index('warehouse_id');
        });

        DB::statement("ALTER TABLE inv.stock_receipt_lines ADD CONSTRAINT stock_receipt_lines_variance_reason_check
            CHECK (variance_reason IS NULL OR variance_reason IN (
                'transit_loss',
                'spillage',
                'temperature_adjustment',
                'measurement_error',
                'other'
            ))");

        foreach (['stock_receipts', 'stock_receipt_lines'] as $tableName) {
            DB::statement("ALTER TABLE inv.{$tableName} ENABLE ROW LEVEL SECURITY");
            DB::statement("
                CREATE POLICY {$tableName}_company_isolation ON inv.{$tableName}
                FOR ALL
                USING (
                    company_id = NULLIF(current_setting('app.current_company_id', true), '')::uuid
                    OR current_setting('app.is_super_admin', true)::boolean = true
                )
            ");
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('inv.stock_receipt_lines');
        Schema::dropIfExists('inv.stock_receipts');
    }
};
