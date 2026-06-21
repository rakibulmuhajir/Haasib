<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('inv.stock_receipt_lines', function (Blueprint $table) {
            $table->string('variance_treatment', 50)->nullable()->after('variance_reason');
            $table->string('claim_status', 50)->nullable()->after('variance_treatment');
            $table->timestamp('claim_received_at')->nullable()->after('claim_status');
            $table->decimal('claim_received_amount', 15, 2)->nullable()->after('claim_received_at');
            $table->uuid('claim_received_account_id')->nullable()->after('claim_received_amount');
            $table->uuid('claim_received_transaction_id')->nullable()->after('claim_received_account_id');

            $table->foreign('claim_received_account_id')
                ->references('id')->on('acct.accounts')
                ->nullOnDelete()->cascadeOnUpdate();
            $table->foreign('claim_received_transaction_id')
                ->references('id')->on('acct.transactions')
                ->nullOnDelete()->cascadeOnUpdate();

            $table->index('variance_treatment');
            $table->index('claim_status');
            $table->index('claim_received_transaction_id');
        });

        DB::statement("ALTER TABLE inv.stock_receipt_lines ADD CONSTRAINT stock_receipt_lines_variance_treatment_check
            CHECK (variance_treatment IS NULL OR variance_treatment IN ('final_loss', 'supplier_claim'))");

        DB::statement("ALTER TABLE inv.stock_receipt_lines ADD CONSTRAINT stock_receipt_lines_claim_status_check
            CHECK (claim_status IS NULL OR claim_status IN ('pending', 'received', 'cancelled'))");
    }

    public function down(): void
    {
        DB::statement('ALTER TABLE inv.stock_receipt_lines DROP CONSTRAINT IF EXISTS stock_receipt_lines_claim_status_check');
        DB::statement('ALTER TABLE inv.stock_receipt_lines DROP CONSTRAINT IF EXISTS stock_receipt_lines_variance_treatment_check');

        Schema::table('inv.stock_receipt_lines', function (Blueprint $table) {
            $table->dropForeign(['claim_received_transaction_id']);
            $table->dropForeign(['claim_received_account_id']);
            $table->dropIndex(['claim_received_transaction_id']);
            $table->dropIndex(['claim_status']);
            $table->dropIndex(['variance_treatment']);
            $table->dropColumn([
                'claim_received_transaction_id',
                'claim_received_account_id',
                'claim_received_amount',
                'claim_received_at',
                'claim_status',
                'variance_treatment',
            ]);
        });
    }
};
