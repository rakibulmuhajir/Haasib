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
        // Create journal batches table
        Schema::create('acct.journal_batches', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('company_id');
            $table->string('batch_number', 20);
            $table->enum('status', ['draft', 'ready', 'scheduled', 'posted', 'void'])->default('draft');
            $table->timestamp('scheduled_post_at')->nullable();
            $table->integer('total_entries')->default(0);
            $table->decimal('total_debits', 20, 2)->default(0);
            $table->decimal('total_credits', 20, 2)->default(0);
            $table->uuid('created_by');
            $table->uuid('approved_by')->nullable();
            $table->timestamp('approved_at')->nullable();
            $table->uuid('posted_by')->nullable();
            $table->timestamp('posted_at')->nullable();
            $table->uuid('voided_by')->nullable();
            $table->timestamp('voided_at')->nullable();
            $table->text('void_reason')->nullable();
            $table->jsonb('attachments')->nullable();
            $table->jsonb('metadata')->nullable();
            $table->timestamps();

            // Foreign keys
            $table->foreign('company_id')->references('id')->on('auth.companies');
            $table->foreign('created_by')->references('id')->on('auth.users');
            $table->foreign('approved_by')->references('id')->on('auth.users');
            $table->foreign('posted_by')->references('id')->on('auth.users');
            $table->foreign('voided_by')->references('id')->on('auth.users');

            // Indexes
            $table->unique(['company_id', 'batch_number']);
            $table->index(['company_id', 'status', 'scheduled_post_at']);
            $table->index(['company_id'])->where('status', '=', 'ready');
        });

        // Create recurring journal templates table
        Schema::create('acct.recurring_journal_templates', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('company_id');
            $table->string('name', 150);
            $table->text('description')->nullable();
            $table->enum('frequency', ['daily', 'weekly', 'monthly', 'quarterly', 'annually', 'custom']);
            $table->string('custom_cron', 100)->nullable();
            $table->timestamp('next_run_at');
            $table->timestamp('last_run_at')->nullable();
            $table->boolean('auto_post')->default(false);
            $table->boolean('active')->default(true);
            $table->uuid('created_by');
            $table->jsonb('metadata')->nullable();
            $table->timestamps();

            // Foreign keys
            $table->foreign('company_id')->references('id')->on('auth.companies');
            $table->foreign('created_by')->references('id')->on('auth.users');

            // Indexes
            $table->unique(['company_id', 'name']);
            $table->index(['active', 'next_run_at']);
        });

        // Create recurring journal template lines table
        Schema::create('acct.recurring_journal_template_lines', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('template_id');
            $table->integer('line_number');
            $table->uuid('account_id');
            $table->enum('debit_credit', ['debit', 'credit']);
            $table->string('amount_formula', 255);
            $table->text('description')->nullable();
            $table->jsonb('metadata')->nullable();
            $table->timestamps();

            // Foreign keys
            $table->foreign('template_id')->references('id')->on('acct.recurring_journal_templates')->onDelete('cascade');
            $table->foreign('account_id')->references('id')->on('acct.accounts');

            // Indexes
            $table->unique(['template_id', 'line_number']);
        });

        // Create journal entry sources table
        Schema::create('acct.journal_entry_sources', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('journal_entry_id');
            $table->uuid('journal_transaction_id')->nullable();
            $table->string('source_type', 100);
            $table->uuid('source_id');
            $table->string('source_reference', 150)->nullable();
            $table->enum('link_type', ['origin', 'supporting', 'reversal']);
            $table->timestamp('created_at');

            // Foreign keys
            $table->foreign('journal_entry_id')->references('id')->on('acct.journal_entries')->onDelete('cascade');
            $table->foreign('journal_transaction_id')->references('id')->on('acct.journal_transactions')->onDelete('cascade');

            // Indexes
            $table->unique(['journal_entry_id', 'source_type', 'source_id']);
            $table->index(['source_type', 'source_id']);
        });

        // Create journal audit log table
        Schema::create('acct.journal_audit_log', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('journal_entry_id');
            $table->enum('event_type', ['created', 'updated', 'posted', 'voided', 'approved', 'reversed', 'attachment_added']);
            $table->uuid('actor_id')->nullable();
            $table->jsonb('payload');
            $table->timestamp('created_at');

            // Foreign keys
            $table->foreign('journal_entry_id')->references('id')->on('acct.journal_entries')->onDelete('cascade');
            $table->foreign('actor_id')->references('id')->on('auth.users');

            // Indexes
            $table->index(['journal_entry_id', 'created_at']);
        });

        // Create triggers for updated_at fields
        DB::statement('
            CREATE TRIGGER journal_batches_updated_at
                BEFORE UPDATE ON acct.journal_batches
                FOR EACH ROW
                EXECUTE FUNCTION auth.set_updated_by();
        ');

        DB::statement('
            CREATE TRIGGER recurring_journal_templates_updated_at
                BEFORE UPDATE ON acct.recurring_journal_templates
                FOR EACH ROW
                EXECUTE FUNCTION auth.set_updated_by();
        ');

        DB::statement('
            CREATE TRIGGER recurring_journal_template_lines_updated_at
                BEFORE UPDATE ON acct.recurring_journal_template_lines
                FOR EACH ROW
                EXECUTE FUNCTION auth.set_updated_by();
        ');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Drop triggers first
        DB::statement('DROP TRIGGER IF EXISTS journal_batches_updated_at ON acct.journal_batches');
        DB::statement('DROP TRIGGER IF EXISTS recurring_journal_templates_updated_at ON acct.recurring_journal_templates');
        DB::statement('DROP TRIGGER IF EXISTS recurring_journal_template_lines_updated_at ON acct.recurring_journal_template_lines');

        // Drop tables
        Schema::dropIfExists('acct.journal_audit_log');
        Schema::dropIfExists('acct.journal_entry_sources');
        Schema::dropIfExists('acct.recurring_journal_template_lines');
        Schema::dropIfExists('acct.recurring_journal_templates');
        Schema::dropIfExists('acct.journal_batches');
    }
};
