<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Consolidated invoices: one document billing a customer for many sales (the daily close's
 * credit-sale invoices) over a period. A record of what was sent -- saved once, never changed
 * (a trigger refuses UPDATE and DELETE), no status. It bills nothing new: the underlying
 * invoices stay what the customer owes.
 *
 *  - acct.consolidated_invoices: the saved document, its lines kept as sent (snapshot).
 *  - acct.consolidated_invoice_items: which invoices each one covered, so the next one can
 *    show "sent in CI-00003".
 *  - acct.customers.billing_contact: the person or office invoices are addressed to.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('acct.customers', function (Blueprint $table) {
            $table->string('billing_contact', 150)->nullable();
        });

        Schema::create('acct.consolidated_invoices', function (Blueprint $table) {
            $table->uuid('id')->primary()->default(DB::raw('public.gen_random_uuid()'));
            $table->uuid('company_id');
            $table->string('number', 30);
            $table->uuid('customer_id');
            $table->date('period_from');
            $table->date('period_to');
            $table->string('title', 60);
            $table->jsonb('bill_to');
            $table->jsonb('billed_by');
            $table->jsonb('columns');   // custom column labels, in order
            $table->jsonb('lines');     // the rows as sent, custom values included
            $table->decimal('total', 15, 2);
            $table->string('currency', 3);
            $table->uuid('created_by_user_id')->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->foreign('company_id')->references('id')->on('auth.companies')->cascadeOnDelete();
            $table->foreign('customer_id')->references('id')->on('acct.customers')->restrictOnDelete();
            $table->foreign('created_by_user_id')->references('id')->on('auth.users')->nullOnDelete();
            $table->unique(['company_id', 'number']);
            $table->index(['company_id', 'customer_id']);
        });

        Schema::create('acct.consolidated_invoice_items', function (Blueprint $table) {
            $table->uuid('id')->primary()->default(DB::raw('public.gen_random_uuid()'));
            $table->uuid('company_id');
            $table->uuid('consolidated_invoice_id');
            $table->uuid('invoice_id');

            $table->foreign('company_id')->references('id')->on('auth.companies')->cascadeOnDelete();
            $table->foreign('consolidated_invoice_id')->references('id')->on('acct.consolidated_invoices')->cascadeOnDelete();
            // No foreign key to the invoice: Edit day deletes and re-creates a day's invoices, and
            // what was sent must neither block that nor disappear with it. The lines keep the numbers.
            $table->unique(['consolidated_invoice_id', 'invoice_id']);
            $table->index(['company_id', 'invoice_id']);
        });

        foreach (['consolidated_invoices', 'consolidated_invoice_items'] as $name) {
            DB::statement("ALTER TABLE acct.{$name} ENABLE ROW LEVEL SECURITY");
            DB::statement("
                CREATE POLICY {$name}_policy ON acct.{$name}
                FOR ALL USING (
                    company_id = current_setting('app.current_company_id', true)::uuid
                    OR current_setting('app.is_super_admin', true)::boolean = true
                )
            ");
        }

        // What was sent stays as it was sent.
        DB::unprepared(<<<'SQL'
CREATE OR REPLACE FUNCTION acct.consolidated_invoice_is_final() RETURNS trigger LANGUAGE plpgsql AS $$
BEGIN
    RAISE EXCEPTION 'A consolidated invoice is a record of what was sent and cannot be changed';
END $$;
CREATE TRIGGER consolidated_invoices_final BEFORE UPDATE OR DELETE ON acct.consolidated_invoices
    FOR EACH ROW EXECUTE FUNCTION acct.consolidated_invoice_is_final();
CREATE TRIGGER consolidated_invoice_items_final BEFORE UPDATE OR DELETE ON acct.consolidated_invoice_items
    FOR EACH ROW EXECUTE FUNCTION acct.consolidated_invoice_is_final();
SQL);
    }

    public function down(): void
    {
        DB::unprepared('DROP TRIGGER IF EXISTS consolidated_invoice_items_final ON acct.consolidated_invoice_items');
        DB::unprepared('DROP TRIGGER IF EXISTS consolidated_invoices_final ON acct.consolidated_invoices');
        Schema::dropIfExists('acct.consolidated_invoice_items');
        Schema::dropIfExists('acct.consolidated_invoices');
        DB::unprepared('DROP FUNCTION IF EXISTS acct.consolidated_invoice_is_final()');
        Schema::table('acct.customers', function (Blueprint $table) {
            $table->dropColumn('billing_contact');
        });
    }
};
