<?php

namespace Database\Seeders;

use App\Models\Company;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class CommandConfigurationSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $this->command->info('Seeding Command Palette configurations...');

        try {
            DB::transaction(function () {
                $companies = Company::all();

                foreach ($companies as $company) {
                    $this->seedConfigurationsForCompany($company);
                }
            });

            $this->command->info('Command Palette configurations seeded successfully!');
        } catch (\Exception $e) {
            Log::error('Failed to seed Command Palette configurations', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            $this->command->error('Failed to seed Command Palette configurations: '.$e->getMessage());
            throw $e;
        }
    }

    /**
     * Seed configurations for a specific company
     */
    private function seedConfigurationsForCompany(Company $company): void
    {
        $environments = ['local', 'staging', 'production'];

        foreach ($environments as $environment) {
            $config = $this->getEnvironmentConfiguration($environment);

            DB::table('command_configurations')->updateOrInsert(
                [
                    'company_id' => $company->id,
                    'environment' => $environment,
                ],
                [
                    'configuration' => json_encode($config),
                    'is_active' => true,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]
            );

            $this->command->line("  - {$environment} configuration for company: {$company->name}");
        }
    }

    /**
     * Get environment-specific configuration
     */
    private function getEnvironmentConfiguration(string $environment): array
    {
        $baseConfig = [
            'max_execution_time' => 30,
            'enable_analytics' => true,
            'cache_timeout' => 3600,
            'rate_limiting' => [
                'enabled' => true,
                'max_attempts' => 60,
                'decay_minutes' => 1,
            ],
            'suggestions' => [
                'enabled' => true,
                'max_suggestions' => 10,
                'confidence_threshold' => 0.7,
            ],
            'security' => [
                'validate_permissions' => true,
                'audit_all_executions' => true,
                'sanitize_parameters' => true,
            ],
        ];

        return match ($environment) {
            'local' => array_merge($baseConfig, [
                'debug_mode' => true,
                'max_execution_time' => 300,
                'enable_analytics' => false,
                'rate_limiting' => array_merge($baseConfig['rate_limiting'], [
                    'enabled' => false,
                ]),
                'suggestions' => array_merge($baseConfig['suggestions'], [
                    'confidence_threshold' => 0.5,
                ]),
            ]),

            'staging' => array_merge($baseConfig, [
                'debug_mode' => true,
                'max_execution_time' => 60,
                'enable_analytics' => true,
                'suggestions' => array_merge($baseConfig['suggestions'], [
                    'confidence_threshold' => 0.6,
                ]),
            ]),

            'production' => array_merge($baseConfig, [
                'debug_mode' => false,
                'max_execution_time' => 30,
                'enable_analytics' => true,
                'cache_timeout' => 7200,
                'security' => array_merge($baseConfig['security'], [
                    'max_parameter_size' => 10240,
                    'allowed_mime_types' => ['application/json', 'text/plain'],
                ]),
            ]),

            default => $baseConfig,
        };
    }
}
