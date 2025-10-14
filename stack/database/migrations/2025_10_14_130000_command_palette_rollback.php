<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class
{
    /**
     * Run the rollback of Command Palette migrations.
     * This should be called in the down() method of the last migration.
     */
    public static function rollbackCommandPalette(): void
    {
        $migrations = [
            '2025_10_14_120200_add_command_performance_indexes',
            '2025_10_14_120100_create_command_analytics_table',
            '2025_10_14_120000_create_command_configurations_table',
            '2025_10_13_165756_create_command_templates_table',
            '2025_10_13_165756_create_command_history_table',
            '2025_10_13_165755_create_command_executions_table',
            '2025_10_13_165707_create_commands_table',
        ];

        // Rollback in reverse order
        foreach (array_reverse($migrations) as $migration) {
            try {
                DB::table('migrations')
                    ->where('migration', $migration)
                    ->delete();

                echo "Rolled back migration: {$migration}\n";
            } catch (\Exception $e) {
                echo "Failed to rollback migration {$migration}: {$e->getMessage()}\n";
            }
        }

        // Drop tables in correct order (respecting foreign keys)
        $tables = [
            'command_analytics',
            'command_configurations',
            'command_templates',
            'command_history',
            'command_executions',
            'commands',
        ];

        foreach ($tables as $table) {
            try {
                if (DB::getSchemaBuilder()->hasTable($table)) {
                    DB::getSchemaBuilder()->drop($table);
                    echo "Dropped table: {$table}\n";
                }
            } catch (\Exception $e) {
                echo "Failed to drop table {$table}: {$e->getMessage()}\n";
            }
        }
    }
};
