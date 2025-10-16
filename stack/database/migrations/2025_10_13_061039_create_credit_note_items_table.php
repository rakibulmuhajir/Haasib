<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades.Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('acct.credit_note_items', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('credit_note_id');
            $table->text('description');
            $table->decimal('quantity', 10, 2);
            $table->decimal('unit_price', 15, 2);
            $table->decimal('tax_rate', 5, 2)->default(0);
            $table->decimal('discount_amount', 15, 2)->default(0);
            $table->decimal('total_amount', 15, 2);
            $table->timestamps();

            // Foreign key
            $table->foreign('credit_note_id')->references('id')->on('acct.credit_notes')->onDelete('cascade');

            // Indexes
            $table->index(['credit_note_id']);
        });

        DB::statement('ALTER TABLE acct.credit_note_items ENABLE ROW LEVEL SECURITY');
        DB::statement('
            ALTER TABLE acct.credit_note_items
            ADD CONSTRAINT credit_note_items_amounts_positive
            CHECK (
                quantity >= 0
                AND unit_price >= 0
                AND discount_amount >= 0
                AND total_amount >= 0
            )
        ');
        DB::statement("
            CREATE POLICY credit_note_items_company_policy
            ON acct.credit_note_items
            FOR ALL
            USING (
                EXISTS (
                    SELECT 1
                    FROM acct.credit_notes cn
                    WHERE cn.id = credit_note_id
                      AND cn.company_id = current_setting('app.current_company_id')::uuid
                )
            )
            WITH CHECK (
                EXISTS (
                    SELECT 1
                    FROM acct.credit_notes cn
                    WHERE cn.id = credit_note_id
                      AND cn.company_id = current_setting('app.current_company_id')::uuid
                )
            )
        ");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::statement('DROP POLICY IF EXISTS credit_note_items_company_policy ON acct.credit_note_items');
        DB::statement('ALTER TABLE acct.credit_note_items DISABLE ROW LEVEL SECURITY');
        Schema::dropIfExists('acct.credit_note_items');
    }
};
