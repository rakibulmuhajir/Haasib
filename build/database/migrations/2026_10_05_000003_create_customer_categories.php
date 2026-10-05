<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Customer categories: the owner's own way of grouping customers (no defaults are created).
 * A customer sits in at most one; a category in use cannot be deleted.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('acct.customer_categories', function (Blueprint $table) {
            $table->uuid('id')->primary()->default(DB::raw('public.gen_random_uuid()'));
            $table->uuid('company_id');
            $table->string('name', 100);
            $table->text('description')->nullable();
            $table->timestamps();

            $table->foreign('company_id')->references('id')->on('auth.companies')->cascadeOnDelete()->cascadeOnUpdate();
        });

        DB::statement('CREATE UNIQUE INDEX customer_categories_company_name_unique ON acct.customer_categories (company_id, lower(name))');

        Schema::table('acct.customers', function (Blueprint $table) {
            $table->uuid('category_id')->nullable();
            $table->foreign('category_id')->references('id')->on('acct.customer_categories')->restrictOnDelete()->cascadeOnUpdate();
            $table->index(['company_id', 'category_id']);
        });

        DB::statement('ALTER TABLE acct.customer_categories ENABLE ROW LEVEL SECURITY');
        DB::statement('ALTER TABLE acct.customer_categories FORCE ROW LEVEL SECURITY');

        DB::statement("CREATE POLICY customer_categories_super_admin ON acct.customer_categories
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

        DB::statement("CREATE POLICY customer_categories_company_isolation ON acct.customer_categories
            FOR ALL
            USING (company_id = NULLIF(current_setting('app.current_company_id', true), '')::uuid)
            WITH CHECK (company_id = NULLIF(current_setting('app.current_company_id', true), '')::uuid)
        ");
    }

    public function down(): void
    {
        Schema::table('acct.customers', function (Blueprint $table) {
            $table->dropForeign(['category_id']);
            $table->dropIndex(['company_id', 'category_id']);
            $table->dropColumn('category_id');
        });
        Schema::dropIfExists('acct.customer_categories');
    }
};
