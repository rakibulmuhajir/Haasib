<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * A customer can be part of a group: independent buyers (the owners of a group of trolleys, the
 * branches of a firm) who each owe and pay their own money, with one customer standing for the
 * group. One level only -- a group is not itself part of a group (Customer\UpdateAction checks).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('acct.customers', function (Blueprint $table) {
            $table->uuid('parent_customer_id')->nullable();
            $table->foreign('parent_customer_id')->references('id')->on('acct.customers')->nullOnDelete();
            $table->index(['company_id', 'parent_customer_id']);
        });
        DB::statement('ALTER TABLE acct.customers ADD CONSTRAINT customers_parent_not_self CHECK (parent_customer_id IS NULL OR parent_customer_id <> id)');
    }

    public function down(): void
    {
        DB::statement('ALTER TABLE acct.customers DROP CONSTRAINT IF EXISTS customers_parent_not_self');
        Schema::table('acct.customers', function (Blueprint $table) {
            $table->dropForeign(['parent_customer_id']);
            $table->dropIndex(['company_id', 'parent_customer_id']);
            $table->dropColumn('parent_customer_id');
        });
    }
};
