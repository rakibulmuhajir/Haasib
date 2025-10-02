<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('invoices', function (Blueprint $table) {
            // Check if column exists before adding
            if (!Schema::hasColumn('invoices', 'cancellation_reason')) {
                $table->text('cancellation_reason')->nullable()->after('status');
            }
            if (!Schema::hasColumn('invoices', 'cancelled_by')) {
                $table->uuid('cancelled_by')->nullable()->after('cancelled_at');
            }

            // Add index for faster queries on cancelled invoices
            if (!$this->hasIndex('invoices', 'idx_invoices_status_cancelled')) {
                $table->index(['status', 'cancelled_at'], 'idx_invoices_status_cancelled');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('invoices', function (Blueprint $table) {
            if ($this->hasIndex('invoices', 'idx_invoices_status_cancelled')) {
                $table->dropIndex('idx_invoices_status_cancelled');
            }
            $table->dropColumn(['cancellation_reason', 'cancelled_by']);
        });
    }

    private function hasIndex(string $table, string $name): bool
    {
        $result = DB::select("
            SELECT COUNT(*) as count
            FROM pg_indexes
            WHERE schemaname = 'public' AND tablename = ? AND indexname = ?
        ", [$table, $name]);

        return $result[0]->count > 0;
    }
};
