<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/*
 * Customers get a type, the way vendors already have one.
 *
 * The supplier side has carried vendor_type since it was built, so a fuel
 * refinery and a contractor are one table and one posting path, told apart by a
 * label. The buyer side had no equivalent, which is why the umrah module grew a
 * second party table for agents: with nowhere to say "this customer is an
 * agent", it made agents a different kind of thing entirely, and every party
 * fact ended up stored twice.
 *
 * The type is a label. Nothing branches on it -- an agent-typed customer is
 * invoiced, aged and posted exactly like a walk-in. It exists so the register
 * can be filtered and so the umrah agent has something to extend.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('acct.customers', function (Blueprint $table) {
            // walk_in for every existing row: it is the only type that is true
            // of a customer nobody has classified, and it is what the seeded
            // demo customers actually are.
            $table->string('customer_type', 30)->default('walk_in')->after('name');
            $table->index(['company_id', 'customer_type']);
        });
    }

    public function down(): void
    {
        // Qualified, because the Blueprint's drop is not: an index lives in its
        // table's schema and acct is not on the search_path.
        DB::statement('DROP INDEX IF EXISTS acct.customers_company_id_customer_type_index');

        Schema::table('acct.customers', function (Blueprint $table) {
            $table->dropColumn('customer_type');
        });
    }
};
