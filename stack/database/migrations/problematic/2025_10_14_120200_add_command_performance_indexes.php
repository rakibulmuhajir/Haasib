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
        Schema::table('commands', function (Blueprint $table) {
            // Add performance optimization indexes
            $table->index(['company_id', 'category', 'is_active']);
            $table->index(['company_id', 'name', 'is_active']);
            $table->index(['required_permissions']);
            $table->index(['execution_handler']);

            // Add full-text search index for PostgreSQL
            if (DB::connection()->getDriverName() === 'pgsql') {
                DB::statement('CREATE INDEX commands_name_fulltext ON commands USING gin(to_tsvector("english", name))');
                DB::statement('CREATE INDEX commands_description_fulltext ON commands USING gin(to_tsvector("english", description))');
            }
        });

        Schema::table('command_executions', function (Blueprint $table) {
            // Add performance optimization indexes
            $table->index(['company_id', 'executed_at']);
            $table->index(['command_id', 'executed_at']);
            $table->index(['user_id', 'executed_at']);
            $table->index(['status', 'executed_at']);
            $table->index(['execution_time_ms']);
        });

        Schema::table('command_history', function (Blueprint $table) {
            // Add performance optimization indexes
            $table->index(['company_id', 'executed_at']);
            $table->index(['user_id', 'executed_at']);
            $table->index(['execution_status', 'executed_at']);

            // Add full-text search index for PostgreSQL
            if (DB::connection()->getDriverName() === 'pgsql') {
                DB::statement('CREATE INDEX command_history_input_fulltext ON command_history USING gin(to_tsvector("english", input_text))');
                DB::statement('CREATE INDEX command_history_result_fulltext ON command_history USING gin(to_tsvector("english", result_summary))');
            }
        });

        Schema::table('command_templates', function (Blueprint $table) {
            // Add performance optimization indexes
            $table->index(['company_id', 'is_shared']);
            $table->index(['user_id', 'created_at']);
            $table->index(['command_id', 'created_at']);

            // Add full-text search index for PostgreSQL
            if (DB::connection()->getDriverName() === 'pgsql') {
                DB::statement('CREATE INDEX command_templates_name_fulltext ON command_templates USING gin(to_tsvector("english", name))');
                DB::statement('CREATE INDEX command_templates_description_fulltext ON command_templates USING gin(to_tsvector("english", description))');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('commands', function (Blueprint $table) {
            $table->dropIndex(['company_id', 'category', 'is_active']);
            $table->dropIndex(['company_id', 'name', 'is_active']);
            $table->dropIndex(['required_permissions']);
            $table->dropIndex(['execution_handler']);

            if (DB::connection()->getDriverName() === 'pgsql') {
                $table->raw('DROP INDEX IF EXISTS commands_name_fulltext');
                $table->raw('DROP INDEX IF EXISTS commands_description_fulltext');
            }
        });

        Schema::table('command_executions', function (Blueprint $table) {
            $table->dropIndex(['company_id', 'executed_at']);
            $table->dropIndex(['command_id', 'executed_at']);
            $table->dropIndex(['user_id', 'executed_at']);
            $table->dropIndex(['status', 'executed_at']);
            $table->dropIndex(['execution_time_ms']);
        });

        Schema::table('command_history', function (Blueprint $table) {
            $table->dropIndex(['company_id', 'executed_at']);
            $table->dropIndex(['user_id', 'executed_at']);
            $table->dropIndex(['execution_status', 'executed_at']);

            if (DB::connection()->getDriverName() === 'pgsql') {
                $table->raw('DROP INDEX IF EXISTS command_history_input_fulltext');
                $table->raw('DROP INDEX IF EXISTS command_history_result_fulltext');
            }
        });

        Schema::table('command_templates', function (Blueprint $table) {
            $table->dropIndex(['company_id', 'is_shared']);
            $table->dropIndex(['user_id', 'created_at']);
            $table->dropIndex(['command_id', 'created_at']);

            if (DB::connection()->getDriverName() === 'pgsql') {
                $table->raw('DROP INDEX IF EXISTS command_templates_name_fulltext');
                $table->raw('DROP INDEX IF EXISTS command_templates_description_fulltext');
            }
        });
    }
};
