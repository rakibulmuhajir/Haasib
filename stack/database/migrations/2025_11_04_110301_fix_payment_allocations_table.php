<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('acct.payment_allocations', function (Blueprint $table) {
            // Add missing columns for PaymentAllocationService integration
            $table->string('allocation_method', 50)->default('manual')->after('allocation_date');
            $table->string('allocation_strategy')->nullable()->after('allocation_method');
            $table->uuid('created_by_user_id')->nullable()->after('metadata');

            // Add foreign key for user tracking
            $table->foreign('created_by_user_id')->references('id')->on('auth.users')->onDelete('set null');
        });

        // Add indexes for new columns
        Schema::table('acct.payment_allocations', function (Blueprint $table) {
            $table->index(['allocation_method']);
            $table->index(['allocation_strategy']);
            $table->index(['created_by_user_id']);
            $table->index(['company_id', 'allocation_method']);
        });

        // Add constraints
        DB::statement('
            ALTER TABLE acct.payment_allocations
            ADD CONSTRAINT payment_allocations_valid_method
            CHECK (allocation_method IN (\'manual\', \'automatic\', \'fifo\', \'lifo\', \'proportional\', \'overdue_first\', \'largest_first\', \'smallest_first\'))
        ');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('acct.payment_allocations', function (Blueprint $table) {
            $table->dropForeign(['created_by_user_id']);
            $table->dropIndex(['allocation_method']);
            $table->dropIndex(['allocation_strategy']);
            $table->dropIndex(['created_by_user_id']);
            $table->dropIndex(['company_id', 'allocation_method']);

            $table->dropColumn('allocation_method');
            $table->dropColumn('allocation_strategy');
            $table->dropColumn('created_by_user_id');
        });

        DB::statement('DROP CONSTRAINT IF EXISTS payment_allocations_valid_method');
    }
};