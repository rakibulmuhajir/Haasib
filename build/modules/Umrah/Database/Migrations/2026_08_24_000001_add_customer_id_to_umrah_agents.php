<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Nullable, not backfilled. An existing agent has no customer record to
 * point at, and inventing one would put a duplicate party on the books.
 * The booking command requires the link and says so when it is missing.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('umrah.agents', function (Blueprint $table) {
            $table->uuid('customer_id')->nullable()->after('company_id');
            $table->foreign('customer_id')
                ->references('id')->on('acct.customers')
                ->nullOnDelete()->cascadeOnUpdate();
            $table->index(['company_id', 'customer_id']);
        });
    }

    public function down(): void
    {
        // DROP INDEX takes no table clause, so it resolves the index name
        // against the connection's search_path (public,acct) rather than
        // the umrah schema the index actually lives in. Qualify it
        // explicitly rather than relying on Blueprint::dropIndex().
        DB::statement('DROP INDEX IF EXISTS umrah.umrah_agents_company_id_customer_id_index');

        Schema::table('umrah.agents', function (Blueprint $table) {
            $table->dropForeign(['customer_id']);
            $table->dropColumn('customer_id');
        });
    }
};
