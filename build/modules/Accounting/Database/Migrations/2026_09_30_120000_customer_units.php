<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Customer units: a customer's own vehicles, sites, rooms or departments (e.g. a transport
 * company's trucks "TLF-866", "GAL-1804"). One customer, one balance; a sale can name which
 * unit it was for, instead of the reference column drifting on free-hand spelling.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('acct.customer_units', function (Blueprint $table) {
            $table->uuid('id')->primary()->default(DB::raw('public.gen_random_uuid()'));
            $table->uuid('company_id');
            $table->uuid('customer_id');
            $table->string('name', 60);
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->foreign('company_id')->references('id')->on('auth.companies')->cascadeOnDelete();
            $table->foreign('customer_id')->references('id')->on('acct.customers')->cascadeOnDelete();
            $table->index(['company_id', 'customer_id']);
        });

        // Case-insensitive per customer: "GAL-1804" and "gal-1804" are the same unit.
        DB::statement('CREATE UNIQUE INDEX customer_units_customer_name_unique ON acct.customer_units (customer_id, lower(name))');

        Schema::table('acct.invoices', function (Blueprint $table) {
            $table->uuid('unit_id')->nullable()->after('reference');
            $table->foreign('unit_id')->references('id')->on('acct.customer_units')->nullOnDelete();
            $table->index('unit_id');
        });

        DB::statement('ALTER TABLE acct.customer_units ENABLE ROW LEVEL SECURITY');
        DB::statement("
            CREATE POLICY customer_units_policy ON acct.customer_units
            FOR ALL USING (
                company_id = current_setting('app.current_company_id', true)::uuid
                OR current_setting('app.is_super_admin', true)::boolean = true
            )
        ");
    }

    public function down(): void
    {
        Schema::table('acct.invoices', function (Blueprint $table) {
            $table->dropForeign(['unit_id']);
            $table->dropColumn('unit_id');
        });
        Schema::dropIfExists('acct.customer_units');
    }
};
