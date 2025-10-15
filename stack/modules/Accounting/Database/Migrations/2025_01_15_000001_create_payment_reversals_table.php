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
        Schema::create('invoicing.payment_reversals', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('payment_id');
            $table->uuid('company_id'); // Denormalized for RLS performance
            $table->text('reason');
            $table->decimal('reversed_amount', 15, 2);
            $table->string('reversal_method', 50); // void, refund, chargeback
            $table->uuid('initiated_by_user_id');
            $table->timestamp('initiated_at');
            $table->timestamp('settled_at')->nullable();
            $table->string('status', 20)->default('pending'); // pending, completed, rejected
            $table->jsonb('metadata')->nullable(); // Additional reversal context
            $table->timestamps();
            
            // Foreign keys
            $table->foreign('payment_id')
                  ->references('id')
                  ->on('invoicing.payments')
                  ->onDelete('cascade');
                  
            $table->foreign('company_id')
                  ->references('id')
                  ->on('auth.companies')
                  ->onDelete('cascade');
                  
            $table->foreign('initiated_by_user_id')
                  ->references('id')
                  ->on('auth.users')
                  ->onDelete('restrict');
            
            // Indexes for performance
            $table->index(['payment_id', 'status']);
            $table->index(['company_id', 'status']);
            $table->index(['initiated_at', 'status']);
            $table->index(['initiated_by_user_id']);
            $table->index(['status']);
            $table->unique(['payment_id'], 'unique_payment_reversal');
        });
        
        // Enable RLS on the table
        DB::statement('ALTER TABLE invoicing.payment_reversals ENABLE ROW LEVEL SECURITY');
        
        // Create RLS policy
        DB::statement("
            CREATE POLICY payment_reversals_company_policy ON invoicing.payment_reversals
            FOR ALL
            TO authenticated_users
            USING (company_id = current_setting('app.current_company')::uuid)
            WITH CHECK (company_id = current_setting('app.current_company')::uuid)
        ");
        
        // Create admin policy that bypasses RLS
        DB::statement("
            CREATE POLICY payment_reversals_admin_policy ON invoicing.payment_reversals
            FOR ALL
            TO admin_role
            USING (true)
            WITH CHECK (true)
        ");
        
        // Add trigger to automatically populate company_id from payment
        DB::statement("
            CREATE OR REPLACE FUNCTION invoicing.set_payment_reversal_company_id()
            RETURNS TRIGGER AS $$
            BEGIN
                NEW.company_id = (
                    SELECT company_id 
                    FROM invoicing.payments 
                    WHERE id = NEW.payment_id
                );
                RETURN NEW;
            END;
            $$ LANGUAGE plpgsql;
        ");
        
        DB::statement("
            CREATE TRIGGER payment_reversals_set_company_id
                BEFORE INSERT OR UPDATE ON invoicing.payment_reversals
                FOR EACH ROW
                EXECUTE FUNCTION invoicing.set_payment_reversal_company_id();
        ");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Drop trigger first
        DB::statement('DROP TRIGGER IF EXISTS payment_reversals_set_company_id ON invoicing.payment_reversals');
        DB::statement('DROP FUNCTION IF EXISTS invoicing.set_payment_reversal_company_id()');
        
        // Drop RLS policies
        DB::statement('DROP POLICY IF EXISTS payment_reversals_admin_policy ON invoicing.payment_reversals');
        DB::statement('DROP POLICY IF EXISTS payment_reversals_company_policy ON invoicing.payment_reversals');
        
        // Disable RLS
        DB::statement('ALTER TABLE invoicing.payment_reversals DISABLE ROW LEVEL SECURITY');
        
        // Drop table
        Schema::dropIfExists('invoicing.payment_reversals');
    }
};