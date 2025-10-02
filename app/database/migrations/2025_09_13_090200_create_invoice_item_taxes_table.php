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
        Schema::create('acct.invoice_item_taxes', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('invoice_item_id');
            $table->string('tax_id', 64)->nullable();
            $table->string('tax_name', 120)->nullable();
            $table->decimal('rate', 7, 4)->default(0);
            $table->decimal('taxable_amount', 15, 2)->default(0);
            $table->decimal('tax_amount', 15, 2)->default(0);
            $table->boolean('is_compound')->default(false);
            $table->integer('compound_order')->default(0);
            $table->json('metadata')->nullable();
            // Idempotency: prevent duplicate tax component on retry
            $table->string('idempotency_key', 128)->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::table('acct.invoice_item_taxes', function (Blueprint $table) {
            $table->foreign('invoice_item_id')->references()->on('acct.invoice_items')->onDelete('cascade');
            $table->index('invoice_item_id', 'idx_iit_item');
        });

        DB::statement('ALTER TABLE acct.invoice_item_taxes ADD CONSTRAINT chk_tax_nonneg CHECK (tax_amount >= 0)');

        // Idempotency unique scope within invoice item
        try {
            DB::statement('CREATE UNIQUE INDEX IF NOT EXISTS invoice_item_taxes_idemp_unique ON invoice_item_taxes (invoice_item_id, idempotency_key) WHERE idempotency_key IS NOT NULL');
        } catch (Throwable $e) { /* ignore */
        }

        // Enable RLS and tenant policy via parent invoice_items -> invoices
        DB::statement('ALTER TABLE acct.invoice_item_taxes ENABLE ROW LEVEL SECURITY');
        DB::statement(<<<'SQL'
            CREATE POLICY invoice_item_taxes_tenant_isolation ON invoice_item_taxes
            USING (EXISTS (
                SELECT 1 FROM invoice_items ii
                JOIN invoices i ON i.invoice_id = ii.invoice_id
                WHERE ii.invoice_item_id = invoice_item_taxes.invoice_item_id
                  AND i.company_id = current_setting('app.current_company', true)::uuid
            ))
            WITH CHECK (EXISTS (
                SELECT 1 FROM invoice_items ii
                JOIN invoices i ON i.invoice_id = ii.invoice_id
                WHERE ii.invoice_item_id = invoice_item_taxes.invoice_item_id
                  AND i.company_id = current_setting('app.current_company', true)::uuid
            ));
        SQL);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('acct.invoice_item_taxes');
    }
};
