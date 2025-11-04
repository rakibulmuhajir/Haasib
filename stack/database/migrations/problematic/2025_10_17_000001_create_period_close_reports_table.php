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
        Schema::create('ledger.period_close_reports', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('period_close_id')->nullable(false);
            $table->jsonb('report_types')->nullable(false);
            $table->string('status', 50)->default('pending');
            $table->uuid('requested_by')->nullable();
            $table->timestamp('requested_at')->nullable();
            $table->timestamp('generated_at')->nullable();
            $table->uuid('completed_by')->nullable();
            $table->jsonb('file_paths')->nullable();
            $table->text('error_message')->nullable();
            $table->jsonb('metadata')->nullable();
            $table->timestamps();

            // Foreign key constraints
            $table->foreign('period_close_id')
                ->references('id')
                ->on('ledger.period_closes')
                ->onDelete('cascade');

            $table->foreign('requested_by')
                ->references('id')
                ->on('auth.users')
                ->onDelete('set null');

            $table->foreign('completed_by')
                ->references('id')
                ->on('auth.users')
                ->onDelete('set null');

            // Indexes
            $table->index(['period_close_id', 'status']);
            $table->index(['status', 'requested_at']);
            $table->index(['requested_by', 'requested_at']);
        });

        // Add RLS policy for the table
        DB::statement('ALTER TABLE ledger.period_close_reports ENABLE ROW LEVEL SECURITY');

        // Create RLS policy
        DB::statement("
            CREATE POLICY period_close_reports_company_policy ON ledger.period_close_reports
            FOR ALL TO authenticated_users
            USING (
                company_id IN (
                    SELECT company_id FROM auth.user_companies 
                    WHERE user_id = current_setting('app.current_user_id')::uuid
                )
            )
        ");

        // Create comments for documentation
        DB::statement("
            COMMENT ON TABLE ledger.period_close_reports IS 'Stores period close report generation requests and results';
        ");

        DB::statement("
            COMMENT ON COLUMN ledger.period_close_reports.report_types IS 'JSON array of report types requested (e.g., [\"income_statement\", \"balance_sheet\"])';
        ");

        DB::statement("
            COMMENT ON COLUMN ledger.period_close_reports.status IS 'Report generation status: pending, processing, completed, failed, cancelled';
        ");

        DB::statement("
            COMMENT ON COLUMN ledger.period_close_reports.file_paths IS 'JSON object mapping report types to file paths';
        ");

        DB::statement("
            COMMENT ON COLUMN ledger.period_close_reports.metadata IS 'JSON metadata including generation context and results';
        ");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('ledger.period_close_reports');
    }
};
