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
        Schema::create('acct.journal_entries', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('company_id');
            $table->string('entry_number')->unique();
            $table->date('entry_date');
            $table->text('description');
            $table->string('reference')->nullable();
            $table->string('status', 20)->default('draft'); // draft, submitted, approved, posted, void
            $table->uuid('created_by_id')->nullable();
            $table->uuid('approved_by_id')->nullable();
            $table->timestamp('approved_at')->nullable();
            $table->timestamp('posted_at')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();

            // Foreign key to auth.users
            $table->foreign('created_by_id')->references('id')->on('auth.users')->onDelete('set null');
            $table->foreign('approved_by_id')->references('id')->on('auth.users')->onDelete('set null');

            // Indexes
            $table->index(['company_id']);
            $table->index(['company_id', 'entry_number']);
            $table->index(['company_id', 'status']);
            $table->index(['company_id', 'entry_date']);
            $table->index(['status']);
            $table->index(['entry_date']);
            $table->index(['created_by_id']);
            $table->index(['approved_by_id']);
        });

        // Enable RLS
        DB::statement('ALTER TABLE acct.journal_entries ENABLE ROW LEVEL SECURITY');

        // Create RLS policies
        DB::statement("
            CREATE POLICY journal_entries_company_policy ON acct.journal_entries
            FOR ALL
            USING (
                company_id = current_setting('app.current_company_id', true)::uuid
                OR current_setting('app.is_super_admin', true)::boolean = true
            )
            WITH CHECK (
                company_id = current_setting('app.current_company_id', true)::uuid
                OR current_setting('app.is_super_admin', true)::boolean = true
            )
        ");

        // Add constraints
        DB::statement('
            ALTER TABLE acct.journal_entries
            ADD CONSTRAINT journal_entries_valid_status
            CHECK (status IN (\'draft\', \'submitted\', \'approved\', \'posted\', \'void\'))
        ');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::statement('DROP POLICY IF EXISTS journal_entries_company_policy ON acct.journal_entries');
        DB::statement('ALTER TABLE acct.journal_entries DISABLE ROW LEVEL SECURITY');
        Schema::dropIfExists('acct.journal_entries');
    }
};