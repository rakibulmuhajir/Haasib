<?php

namespace App\Console\Commands;

use App\Services\SetupService;
use Illuminate\Console\Command;
use Illuminate\Support\Str;

class SetupInitialize extends Command
{
    protected $signature = 'setup:initialize
        {--name=Super Administrator : Name for the initial super admin}
        {--email=admin@haasib.local : Email for the initial super admin}
        {--password= : Password for the initial super admin (generated if omitted)}
        {--company=Acme Corporation : Initial company name}
        {--slug= : Slug for the initial company (generated if omitted)}
        {--currency=USD : Base currency for the initial company}
        {--force : Run without confirmation even if data exists}';

    protected $description = 'Boot the platform with an inaugural user and company.';

    public function handle(SetupService $setupService): int
    {
        if ($setupService->isSystemInitialized() && ! $this->option('force')) {
            if (! $this->confirm('System data already exists. Do you want to continue and add another bootstrap set?')) {
                $this->info('Initialization aborted.');

                return self::INVALID;
            }
        }

        $password = $this->option('password') ?: Str::random(16);

        $userData = [
            'name' => $this->option('name'),
            'email' => $this->option('email'),
            'password' => $password,
        ];

        $companyData = [
            'name' => $this->option('company'),
            'slug' => $this->option('slug') ?: Str::slug($this->option('company')),
            'base_currency' => Str::upper($this->option('currency')),
        ];

        [$user, $company] = $setupService->initializeSystem($userData, $companyData);

        $this->line('');
        $this->info('✅ Haasib platform initialized!');
        $this->line('');
        $this->table(['Type', 'Identifier'], [
            ['User', $user->email],
            ['Company', $company->name],
            ['Password', $password],
        ]);
        $this->warn('Make sure to rotate this password immediately.');

        return self::SUCCESS;
    }
}
